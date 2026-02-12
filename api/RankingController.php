<?php
require_once 'Ranking.php';

class RankingController {
    private $db;
    private $ranking;

    public function __construct($db) {
        $this->db = $db;
        $this->ranking = new Ranking($db);
    }

    public function getRanking() {
        $ranking = $this->ranking->getCurrentMonthRanking();

        http_response_code(200);
        echo json_encode(["ranking" => $ranking]);
    }
}
?>
