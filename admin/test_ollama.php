<?php
// Test simple de Ollama
require_once 'ollama_menu_parser.php';

$parser = new OllamaMenuParser();
$info = $parser->getSystemInfo();
header('Content-Type: application/json');
echo json_encode($info, JSON_PRETTY_PRINT);
