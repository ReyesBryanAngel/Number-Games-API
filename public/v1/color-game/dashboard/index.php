<?php

// for events selection of boards after validating of jwt
require('../../../../config/config-colorgame.php');

header('Content-Type: application/json; charset=utf-8');
header('Content-Type: application/json');

$input = @json_decode(@file_get_contents('php://input'), true);

if ($input == null) {
    // FAILED
    $result = array(
        "status" => 'failed',
        "message" => "Incorrect Parameters!"
    );

    http_response_code(400);
    $code = 400;
} else {

    $sql_login = "SELECT * FROM boards WHERE `board_id` = :board_id AND `board_status` = 'open'";
    $sthAuth = $conn->prepare($sql_login);
    $sthAuth->bindParam(':board_id', $input['board_id']);
    $sthAuth->execute();
    $check_board = $sthAuth->fetch(PDO::FETCH_ASSOC);

    if ($check_board) {
        $sql_login = "SELECT * FROM operators WHERE `operator_id` = :operatorID";
        $sthAuth = $conn->prepare($sql_login);
        $sthAuth->bindParam(':operatorID', $input['operatorID']);
        $sthAuth->execute();
        $rows = $sthAuth->fetch(PDO::FETCH_ASSOC);

        if ($rows == null) {
            // FAILED
            $result = array(
                "status" => 'failed',
                "message" => "Operator does not exist!"
            );
            http_response_code(400);
            $code = 400;
        } else {
            $jwt_token = $input['token'];
            $secret_key = $rows['game_api_key'];
            $verify = verifyJWT($jwt_token, $secret_key);

            if ($verify) {
                $check_user = "SELECT * FROM summation_transaction WHERE `player_id` = :player_id";
                $checkUserAuth = $conn->prepare($check_user);
                $checkUserAuth->bindParam(':player_id', $verify["playerUsername"], PDO::PARAM_STR);
                $checkUserAuth->execute();
                $rows = $checkUserAuth->fetch(PDO::FETCH_ASSOC);

                if ($rows['current_balance'] > 0) {
                    // all about users
                    $sql_user_details = "SELECT player_id, current_balance FROM summation_transaction WHERE `player_id` = :player_id";
                    $userdetailsStat = $conn->prepare($sql_user_details);
                    $userdetailsStat->bindParam(':player_id', $verify["playerUsername"], PDO::PARAM_STR);
                    $userdetailsStat->execute();
                    $user_details = $userdetailsStat->fetch(PDO::FETCH_ASSOC);
                    $user_details['minbetlimit'] = $verify['minbetlimit'];
                    $user_details['maxbetlimit'] = $verify['maxbetlimit'];

                    // get dice details
                    $get_dices = "SELECT * FROM dices WHERE `dice_status` = 'active'";
                    $dicestatement = $conn->prepare($get_dices);
                    $dicestatement->execute();
                    $dice_details = $dicestatement->fetchAll(PDO::FETCH_ASSOC);

                    // Initialize board color array
                    $board_color = [
                        "red" => ["total_bet" => 0, "total_count" => 0, "bets" => []],
                        "blue" => ["total_bet" => 0, "total_count" => 0, "bets" => []],
                        "yellow" => ["total_bet" => 0, "total_count" => 0, "bets" => []],
                        "white" => ["total_bet" => 0, "total_count" => 0, "bets" => []],
                        "green" => ["total_bet" => 0, "total_count" => 0, "bets" => []],
                        "pink" => ["total_bet" => 0, "total_count" => 0, "bets" => []]
                    ];
                    $get_current_rounds = "SELECT round_id, round_count, board_id, winning_result, round_status, round_created,round_started,round_closed 
                    FROM rounds 
                    WHERE `board_id` = :board_id order by round_id desc limit 1";
                    $currentroundStat = $conn->prepare($get_current_rounds);
                    $currentroundStat->bindValue(':board_id', $input['board_id']);
                    $currentroundStat->execute();
                    $current_round = $currentroundStat->fetch(PDO::FETCH_ASSOC);
                    
                    if($current_round){
                    // Fetch all bets for the specified board and round
                    $get_bets = "SELECT bet_id, player_id, round_count, bet, bet_amount, bet_status, time_of_bet FROM bets WHERE board_id = :board_id AND round_id = :round_id ORDER BY bet_id DESC";
                    $betStatement = $conn->prepare($get_bets);
                    $betStatement->bindValue(':board_id', $current_round['board_id']);
                    $betStatement->bindValue(':round_id', $current_round['round_id']);
                    $betStatement->execute();
                    $bets_history = $betStatement->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($bets_history as $bet) {
                        $color = strtolower($bet['bet']); // Assuming the bet field holds the color information
                        $bet_amount = $bet['bet_amount'];
                        $is_my_bet = ($bet['player_id'] == $verify["playerUsername"]) ? 1 : 0;

                        // Update board color data
                        $board_color[$color]["total_bet"] += $bet_amount;
                        $board_color[$color]["total_count"] += 1;
                        $board_color[$color]["bets"][] = [
                            "bet_amount" => $bet_amount,
                            "my_bet" => $is_my_bet
                        ];
                    }

                    // about game details
                    $get_board = "SELECT board_id, event_id, board_title, board_description, video_source, board_status 
                                FROM boards 
                                WHERE `board_id` = :board_id";
                    $boardstatement = $conn->prepare($get_board);
                    $boardstatement->bindValue(':board_id', $input['board_id']);
                    $boardstatement->execute();
                    $board_details = $boardstatement->fetch(PDO::FETCH_ASSOC);

                    

                    $get_round_history = "SELECT round_id, round_count, board_id, winning_result, round_status 
                                            FROM rounds 
                                            WHERE `board_id` = :board_id AND `round_status` = 'finish' 
                                            ORDER BY round_id DESC";
                    $historystatement = $conn->prepare($get_round_history);
                    $historystatement->bindValue(':board_id', $input['board_id']);
                    $historystatement->execute();
                    $round_history = $historystatement->fetchAll(PDO::FETCH_ASSOC);

                    $result = array(
                        "status" => "success",
                        "response_code" => 200,
                        "message" => "Dashboard",
                        "data" => array(
                            "user" => array(
                                'details' => $user_details,
                                'board_color' => $board_color
                            ),
                            "game" => array(
                                'details' => $board_details,
                                'current_round' => $current_round,
                                'round_history' => $round_history
                            ),
                        )
                    );

                    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                    http_response_code(200);
                    $code = 200;
                    
                    }else{
                       $result = array(
                        "status" => 'failed',
                        "message" => "No available rounds",
                        "data" => array("balance" => $rows['current_balance'])
                    );

                    http_response_code(400);
                    $code = 400;
                    }
                } else {
                    $result = array(
                        "status" => 'failed',
                        "message" => "Insufficient funds",
                        "data" => array("balance" => $rows['current_balance'])
                    );

                    http_response_code(400);
                    $code = 400;
                }
            } else {
                // FAILED
                $result = array(
                    "status" => 'failed',
                    "message" => "Wrong Operator / Expired token"
                );

                http_response_code(400);
                $code = 400;
            }
        }
    } else {
        // FAILED
        $result = array(
            "status" => 'failed',
            "message" => "Board is not available!"
        );

        http_response_code(400);
        $code = 400;
    }
}

function verifyJWT($jwt_token, $secret_key) {
    // Split token into parts
    $parts = explode('.', $jwt_token);
    if (count($parts) !== 3) {
        return null; // Return null if token format is invalid
    }

    // Decode base64 payload
    $payload = json_decode(base64_decode($parts[1]), true);

    // Recreate signature
    $signature = base64_decode($parts[2]);
    $expected_signature = hash_hmac('sha256', "$parts[0].$parts[1]", $secret_key, true);

    // Compare signature with expected signature
    if (hash_equals($signature, $expected_signature) && $payload['exp'] >= time()) {
        return $payload; // Return payload data if token is valid
    } else {
        return null; // Return null if token is invalid or expired
    }
}

$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
header($protocol . " $code " . 'OK');
echo json_encode($result);

// CLOSE DATABASE CONNECTION
$conn = null;
?>