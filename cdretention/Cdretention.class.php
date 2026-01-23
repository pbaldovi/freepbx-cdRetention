<?php
namespace FreePBX\modules;

class Cdretention implements \BMO {
    public $message = "";
    private $FreePBX;
    private $db;

    public function __construct($FreePBX = null) {
            if ($FreePBX == null) {
                $FreePBX = \FreePBX::create();
            }
            $this->FreePBX = $FreePBX;
            $this->db = $FreePBX->Database;
        }

        public function install() {
            $this->FreePBX = \FreePBX::create();
            
            // La tabla se crea via module.xml ahora
            
            // Establecer valor por defecto
            if ($this->getConfig('purge_days') === null) {
                $this->setConfig('purge_days', 30);
            }

            // Programar tarea Cron (01:00 AM)
            $this->FreePBX->Cron->add("0 1 * * * /usr/sbin/fwconsole cdretention purge");

            // Limpiar caché de consola
            @unlink('/var/lib/asterisk/bin/console.cache');
        }

        public function uninstall() {
            $this->FreePBX = \FreePBX::create();
            $this->FreePBX->Cron->remove("/usr/sbin/fwconsole cdretention purge");
            // La tabla se maneja por BMO
            $this->FreePBX->Database->query("DROP TABLE IF EXISTS cdretention_settings");
            @unlink('/var/lib/asterisk/bin/console.cache');
        }

        public function doConfigPageInit($page) {
        $this->FreePBX = \FreePBX::create();
        
        // Verificar si la acción viene en POST o REQUEST
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

        if ($action == 'save') {
            if (isset($_POST['purge_days'])) {
                $days = intval($_POST['purge_days']);
                // Validar que sea un número positivo
                if ($days > 0) {
                    $this->setConfig('purge_days', $days);
                    $this->message = '<div class="alert alert-success">Configuración guardada correctamente (' . $days . ' días).</div>';
                } else {
                    $this->message = '<div class="alert alert-danger">Error: El número de días debe ser mayor a 0.</div>';
                }
            }
        } elseif ($action == 'purge_now') {
            $count = $this->purgeOldRecords();
            $this->message = '<div class="alert alert-warning">Purga completada: Se eliminaron ' . $count . ' registros.</div>';
        }
    }

        public function getConfig($key) {
            $this->FreePBX = \FreePBX::create();
            $sql = "SELECT value FROM cdretention_settings WHERE `key` = :key";
            $stmt = $this->FreePBX->Database->prepare($sql);
            $stmt->execute(array(':key' => $key));
            $res = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $res ? $res['value'] : null;
        }

        public function setConfig($key, $value) {
            $this->FreePBX = \FreePBX::create();
            // Aseguramos limpieza eliminando cualquier entrada previa para esta clave
            // Esto soluciona problemas si la tabla perdió su índice único o hay duplicados
            $sqlDel = "DELETE FROM cdretention_settings WHERE `key` = :key";
            $stmtDel = $this->FreePBX->Database->prepare($sqlDel);
            $stmtDel->execute(array(':key' => $key));

            // Insertamos el nuevo valor
            $sql = "INSERT INTO cdretention_settings (`key`, `value`) VALUES (:key, :value)";
            $stmt = $this->FreePBX->Database->prepare($sql);
            $stmt->execute(array(':key' => $key, ':value' => $value));
        }

        public function purgeOldRecords($days = null, $callback = null) {
        $this->FreePBX = \FreePBX::create();
        
        if ($days === null) {
            $days = $this->getConfig('purge_days');
        }
        
        if (!is_numeric($days) || $days < 1) return 0;

        // Calcular fecha de corte exacta para información y consistencia
        $cutoffTimestamp = strtotime("-{$days} days");
        $cutoffDate = date('Y-m-d H:i:s', $cutoffTimestamp);

        $totalDeleted = 0;
        $batchSize = 5000;
        $tables = [
            ['table' => 'asteriskcdrdb.cdr', 'col' => 'calldate', 'pk' => 'AUTO'], // Intentar autodetectar
            ['table' => 'asteriskcdrdb.cel', 'col' => 'eventtime', 'pk' => 'id']
        ];

        foreach ($tables as $t) {
            $cutoffId = null;
            $tableDeletedCount = 0; // Para rastrear si se borró algo en esta tabla
            $pkField = isset($t['pk']) ? $t['pk'] : null;

            // Lógica de Autodetección de Primary Key (para CDR que varía según versión)
            if ($pkField === 'AUTO') {
                $pkField = null; // Reset por seguridad
                try {
                    // Extraemos base de datos y tabla
                    $parts = explode('.', $t['table']);
                    if (count($parts) == 2) {
                        $dbName = $parts[0];
                        $tbName = $parts[1];
                        // Buscamos columna auto_increment
                        $sqlPk = "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tb AND EXTRA = 'auto_increment' LIMIT 1";
                        $stmtPk = $this->FreePBX->Database->prepare($sqlPk);
                        $stmtPk->execute([':db' => $dbName, ':tb' => $tbName]);
                        $foundPk = $stmtPk->fetchColumn();
                        if ($foundPk) {
                            $pkField = $foundPk;
                        }
                    }
                } catch (\Exception $e) {
                    // Si falla la detección, simplemente seguimos con $pkField = null (fallback a fecha)
                }
            }

            // Optimización para tablas con PK (específicamente CEL con millones de registros)
            if (!empty($pkField)) {
                try {
                    // 1. Obtener el ID máximo que cumple la condición de fecha
                    // Esto evita escanear toda la tabla buscando fechas en cada DELETE
                    $sqlMax = "SELECT MAX({$pkField}) FROM {$t['table']} WHERE {$t['col']} < :cutoffDate";
                    $stmtMax = $this->FreePBX->Database->prepare($sqlMax);
                    $stmtMax->execute([':cutoffDate' => $cutoffDate]);
                    $cutoffId = $stmtMax->fetchColumn();
                } catch (\Exception $e) {
                    // Si falla la consulta de MAX (ej: columna no existe), anulamos cutoffId
                    $cutoffId = null;
                }
            }

            // Si tenemos un cutoffId, usamos la estrategia optimizada por PK
            if ($cutoffId) {
                do {
                    $sql = "DELETE FROM {$t['table']} WHERE {$pkField} <= :cutoffId LIMIT :limit";
                    $stmt = $this->FreePBX->Database->prepare($sql);
                    $stmt->bindValue(':cutoffId', $cutoffId);
                    $stmt->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
                    $stmt->execute();
                    
                    $count = $stmt->rowCount();
                    $totalDeleted += $count;
                    $tableDeletedCount += $count;
                    
                    if ($count > 0) {
                        if (is_callable($callback)) {
                            $callback($count, $t['table'], $cutoffDate, $cutoffId);
                        }
                        usleep(50000); // 50ms pause para I/O
                    }
                } while ($count > 0);
            } else {
                // Estrategia fallback (original) para tablas sin PK definida o sin registros encontrados
                // Solo ejecutamos esto si NO se encontró un cutoffId (o no hay PK)
                
                // Si la tabla TIENE PK detectada pero no devolvió cutoffId, significa que no hay nada que borrar.
                // Informamos 0 y continuamos.
                if (!empty($pkField) && $cutoffId === null) {
                    if (is_callable($callback)) {
                        $callback(0, $t['table'], $cutoffDate, null);
                    }
                    continue; 
                }

                do {
                    // Usamos ORDER BY para asegurar que borramos los más antiguos primero
                    $sql = "DELETE FROM {$t['table']} WHERE {$t['col']} < :cutoffDate ORDER BY {$t['col']} ASC LIMIT :limit";
                    $stmt = $this->FreePBX->Database->prepare($sql);
                    $stmt->bindValue(':cutoffDate', $cutoffDate);
                    $stmt->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
                    $stmt->execute();
                    
                    $count = $stmt->rowCount();
                    $totalDeleted += $count;
                    $tableDeletedCount += $count;
                    
                    if ($count > 0) {
                        if (is_callable($callback)) {
                            $callback($count, $t['table'], $cutoffDate, null);
                        }
                        usleep(50000); // 50ms pause para I/O
                    }
                } while ($count > 0);

                // Si usamos fallback y no se borró nada, notificamos también
                if ($tableDeletedCount == 0) {
                    if (is_callable($callback)) {
                        $callback(0, $t['table'], $cutoffDate, null);
                    }
                }
            }
        }
        
        return $totalDeleted;
    }
}
