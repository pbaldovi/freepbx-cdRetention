<?php
namespace FreePBX\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class CdretentionCommand extends Command {
    protected function configure() {
        $this->setName('cdretention')
             ->setDescription('Administrar retención de CDR')
             ->addArgument('action', InputArgument::REQUIRED, 'Acción a realizar (purge)');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $action = $input->getArgument('action');

        if ($action == 'purge') {
            $output->writeln("Iniciando purga de registros antiguos...");
            
            try {
                // Instanciamos FreePBX y accedemos al módulo Cdretention a través de BMO
                $fpbx = \FreePBX::create();
                
                // Verificamos si el módulo está cargado y tiene el método
                if ($fpbx->Cdretention && method_exists($fpbx->Cdretention, 'purgeOldRecords')) {
                    $count = $fpbx->Cdretention->purgeOldRecords(null, function($batchCount, $table, $cutoffDate = '', $cutoffId = null) use ($output) {
                        if ($batchCount == 0) {
                            $output->writeln("<comment>No se encontraron registros antiguos para eliminar en la tabla $table.</comment>");
                            return;
                        }
                        $msg = "<info>Eliminados $batchCount registros de la tabla $table";
                        if ($cutoffDate) {
                            $msg .= " (anteriores a $cutoffDate)";
                        }
                        if ($cutoffId !== null) {
                            $msg .= " [Cutoff ID: $cutoffId]";
                        }
                        $msg .= "...</info>";
                        $output->writeln($msg);
                    });
                    $output->writeln("<info>Purga finalizada. Total de registros eliminados: " . $count . "</info>");
                    return 0; // Command::SUCCESS
                } else {
                    $output->writeln("<error>Error: No se pudo cargar el módulo Cdretention.</error>");
                    return 1; // Command::FAILURE
                }
            } catch (\Exception $e) {
                $output->writeln("<error>Excepción: " . $e->getMessage() . "</error>");
                return 1; // Command::FAILURE
            }
        }

        $output->writeln("<error>Acción desconocida. Use: fwconsole cdretention purge</error>");
        return 1; // Command::FAILURE
    }
}
