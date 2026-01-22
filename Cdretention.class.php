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

    public function install() {
        // Programar tarea diaria a las 01:00 AM
        $this->FreePBX->Cron->add("0 1 * * * /usr/sbin/fwconsole cdrpurger purge");
    }

    public function uninstall() {
        $this->FreePBX->Cron->remove("/usr/sbin/fwconsole cdrpurger purge");
    }

    public function purgeOldRecords($days = null) {
        if ($days === null) {
            $days = $this->FreePBX->Config->get_conf_setting('CDRPURGE_DAYS');
        }
        
        if (!is_numeric($days) || $days < 1) return 0;

        // Limpieza de la tabla CDR en la base de datos asteriskcdrdb
        $sql = "DELETE FROM asteriskcdrdb.cdr WHERE calldate < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(':days' => $days));
        
        return $stmt->rowCount();
    }

    // Permite ejecutar 'fwconsole cdrpurger purge' desde SSH
    public function abmoconsolecommands() {
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