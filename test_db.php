<?php
$env = file_get_contents('.env');
preg_match('/DB_DATABASE=(.*)/', $env, $db);
preg_match('/DB_USERNAME=(.*)/', $env, $user);
preg_match('/DB_PASSWORD=(.*)/', $env, $pass);
$pdo = new PDO('mysql:host=127.0.0.1;dbname='.trim($db[1]), trim($user[1]), trim($pass[1]));
$stmt = $pdo->query("SELECT id, session_id, role, context_data FROM ai_chat_memories ORDER BY id DESC LIMIT 10");
foreach($stmt as $row) {
    if(strpos($row['context_data'], 'local_uploads') !== false) {
        echo "YES - FOUND UPLOADS!\n";
        echo $row['context_data'] . "\n\n";
    }
}
echo "Script finished";
