<?php
session_start();
include 'db-connect.php';

//Wenn der Host das Spiel verlaesst wird das vermerkt, sodass Teilnehmer Host werden koennen
if(isset($_SESSION['isHost']) && $_SESSION['isHost']){
    $query = $conn->prepare("UPDATE session SET hasHost=0 WHERE SpielID = ?");
    $query->bind_param("s",$_SESSION['SpielID']);
    $query->execute();
    $conn->close();
}

session_destroy();
header('Location: index');
exit;
?>