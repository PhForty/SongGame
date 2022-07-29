<?php
  try {
    $conn = new mysqli("database", "root", "wrjkn422", "song_game");
  }
  catch (exception $e) {
    echo "Die Datenbankverbindung hat leider nicht geklappt.";
  }
?>