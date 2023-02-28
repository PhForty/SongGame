<?php
session_start();
include 'db-connect.php';

if(!isset($_SESSION['StartedGame']) || (isset($_SESSION['StartedGame']) && $_SESSION['StartedGame'] === 0) || (isset($_SESSION['isHost']) && !$_SESSION['isHost'])) {
    header('Location: eingabe');
  }

if(isset($_POST['deleteAll']) && $_POST['deleteAll'] == "yes"){
    $query = $conn->prepare("DELETE FROM songs WHERE `SpielID` = ?");
    $query->bind_param("s",$_SESSION['SpielID']);
    if ($query->execute()) {
        //Alle erfolgreich gelöscht
    }
}
$query = $conn->prepare("SELECT youtube_link, wasViewed FROM songs WHERE `SpielID` = ?");
$query->bind_param("s",$_SESSION['SpielID']);
$query->execute();
$resultListAll = $query->get_result();    
$conn->close();
?>

<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" href="favicon.ico">
        <link rel="stylesheet" type="text/css" href="pure-min.css" media="screen" />
        <link rel="stylesheet" type="text/css" href="style.css" media="screen" />
        <title>AdminView</title>
    </head>
    <body>
    <div id="wrapper">
        <header>
        <nav class="pure-menu pure-menu-horizontal">
          <ul class="pure-menu-list">
            <?php
            if(isset($_SESSION['isHost']) && $_SESSION['isHost']){
              echo "<li class='pure-menu-item'>";
              echo "<a class='pure-menu-link' href='eingabe'>Eingabe</a></li>";
              echo "<li class='pure-menu-item'>";
              echo "<a class='pure-menu-link' href='viewer'>Spiel</a></li>";
              echo "<li class='pure-menu-item'>";
              echo "<a class='pure-menu-link' href='admin-view'>Admin-View</a></li>";
            }
            ?>
            <li class="pure-menu-item">
            <a class="pure-menu-link" href="logout">Logout</a></li>
          </ul>
        </nav>
        </header>
        <main>
            <?php 
            if(isset($_SESSION['isHost']) && !$_SESSION['isHost']){
                echo "<p>Spiel: <b>{$_SESSION['SpielID']}</b> - Rolle: <b>Teilnehmer</b></p>";
            } else {
                echo "<p>Spiel: <b>{$_SESSION['SpielID']}</b> - Rolle: <b>Gastgeber</b></p>";
            }?>
                <button type="button" class="collapsible">Bereits abgespielte Links</button>
                <div class="content">
                    <?php
                        if (isset($resultListAll)){
                            foreach ($resultListAll as $link) {
                                if($link['wasViewed'] === 1){echo "<p>".htmlspecialchars($link['youtube_link'], ENT_QUOTES)."</p>";}
                            }
                        }
                    ?>
                </div>
                <br><br>
                <button type="button" class="collapsible">Noch nicht abgespielte Links</button>
                <div class="content">
                    <?php
                        if (isset($resultListAll)){
                            foreach ($resultListAll as $link) {
                                if($link['wasViewed'] === 0){echo "<p>".htmlspecialchars($link['youtube_link'], ENT_QUOTES)."</p>";}
                            }
                        }
                    ?>
                </div>
            <br><br>
            <form action="admin-view" method="post">
                <button class="button-error pure-button" name="deleteAll" type="submit" value="yes">ALLE LINKS LÖSCHEN</button>
            </form>
        </main>
    </div>
    </body>
    <script src="utilities.js"></script>
</html>