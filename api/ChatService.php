<?php

class ChatService {
    private $apiKey;
    private $apiUrl;

    public function __construct() {
        $this->apiKey = env('AI_API_KEY');
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $this->apiKey;
    }

    public function sendMessage($message, $context = []) {
        if (empty($this->apiKey)) {
            return "Error: La clave de API de IA no está configurada.";
        }

        $payload = [
            'contents' => [
                'parts' => [
                    ['text' => $this->buildPrompt($message, $context)]
                ]
            ]
        ];

        return $this->sendRequest($payload);
    }

    public function generateRecommendations($salesData) {
        if (empty($this->apiKey)) {
            // ... (manejo de error)
        }

        $prompt = $this->buildRecommendationPrompt($salesData);
        $payload = [
            'contents' => [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ];

        $response = $this->sendRequest($payload);
        return $this->parseRecommendations($response);
    }

    private function sendRequest($payload) {
        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode >= 400) {
            return "Error en la API de IA: " . $response;
        }

        $result = json_decode($response, true);
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? "No se pudo obtener una respuesta de la IA.";
    }

    private function buildPrompt($message, $context) {
        // Un prompt simple por ahora
        return "Eres un asistente de negocios para un restaurante. Responde al siguiente mensaje del dueño del restaurante: '{$message}'";
    }

    private function buildRecommendationPrompt($salesData) {
        $stats = json_encode($salesData['estadisticas']);
        return "Eres un asistente de negocios para un restaurante. Basado en los siguientes datos de ventas: {$stats}, genera 2 recomendaciones para aumentar las ventas. Formatea la salida como un JSON con una clave 'recomendaciones' que sea un array de objetos, cada objeto con 'titulo', 'descripcion', 'impacto' ('alto', 'medio', 'bajo'), y 'categoria'.";
    }

    private function parseRecommendations($response) {
        // Intenta decodificar la respuesta JSON de la IA
        $decoded = json_decode($response, true);
        return $decoded['recomendaciones'] ?? [['titulo' => 'Recomendación Genérica', 'descripcion' => 'Mejora la calidad de tus productos.', 'impacto' => 'medio', 'categoria' => 'Calidad']];
    }
}
?>