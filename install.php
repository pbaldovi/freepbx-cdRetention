<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

// Establecer un valor por defecto si no existe
if ($current = $FreePBX->Config->get_conf_setting('CDRPURGE_DAYS') === null) {
    $FreePBX->Config->set_conf_setting('CDRPURGE_DAYS', 30);
}