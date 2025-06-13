<?php

function log_message($level, $message) {
    $logFile = __DIR__ . '/application.log'; // Шлях до файлу логу
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function log_debug($message) {
    log_message('DEBUG', $message);
}

function log_info($message) {
    log_message('INFO', $message);
}

function log_warning($message) {
    log_message('WARNING', $message);
}

function log_error($message) {
    log_message('ERROR', $message);
}

function log_critical($message) {
    log_message('CRITICAL', $message);
}

?>