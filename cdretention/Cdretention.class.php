<?php
namespace FreePBX\modules;

class Cdretention implements \BMO {
    public function __construct($FreePBX = null) {
        if ($FreePBX == null) {
            throw new \Exception("No FreePBX Object");
        }
        $this->FreePBX = $FreePBX;
        // El CDR suele estar en una base de datos distinta (asteriskcdrdb)
        $this->db = $this->FreePBX->Database; 
    }

    public function getConfig($key) {
        $sql = "SELECT value FROM cdretention_settings WHERE `key` = :key";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':key' => $key));
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $res ? $res['value'] : null;
    }

    public function setConfig($key, $value) {
        $sql = "INSERT INTO cdretention_settings (`key`, `value`) VALUES (:key, :value) ON DUPLICATE KEY UPDATE `value` = :value";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':key' => $key, ':value' => $value));
    }

    public function install() {
        // Crear tabla de configuración local si no existe
        $sql = "CREATE TABLE IF NOT EXISTS cdretention_settings (
            `key` VARCHAR(50) PRIMARY KEY,
            `value` VARCHAR(255)
        )";
        $this->db->query($sql);

        // Programar tarea diaria a las 01:00 AM
        $this->FreePBX->Cron->add("0 1 * * * /usr/sbin/fwconsole cdretention purge");


        // 2. Establecer el valor por defecto si no existe
        if ($this->getConfig('purge_days') === null) {
            $this->setConfig('purge_days', 30);
        }

    }

    public function uninstall() {
        $this->FreePBX->Cron->remove("/usr/sbin/fwconsole cdretention purge");
        $this->db->query("DROP TABLE IF EXISTS cdretention_settings");
    }

    public function purgeOldRecords($days = null) {
        if ($days === null) {
            $days = $this->getConfig('purge_days');
        }
        
        if (!is_numeric($days) || $days < 1) return 0;

        // Limpieza de la tabla CDR en la base de datos asteriskcdrdb
        $sql = "DELETE FROM asteriskcdrdb.cdr WHERE calldate < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':days' => $days));
        
        return $stmt->rowCount();
    }

    // Permite ejecutar 'fwconsole cdretention purge' desde SSH
    public function getConsoleCommands() {
        return array(
            'purge' => array(
                'description' => 'Purga manual de CDR',
                'help' => 'Borra registros anteriores a los días configurados',
                'callback' => function($output) {
                    $count = $this->purgeOldRecords();
                    $output->writeln("Éxito: Se eliminaron $count registros.");
                }
            )
        );
    }
}