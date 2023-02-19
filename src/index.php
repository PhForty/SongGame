<?php
session_start();
include 'db-connect.php';

if(isset($_POST['SpielID']) && strlen($_POST['SpielID']) == 5){

    //Pruefe auf Existenz SpielID in Datenbank
    $query = $conn->prepare("SELECT * FROM session WHERE `SpielID` = ?");
    $query->bind_param("s",strtoupper($_POST['SpielID']));
    $query->execute();
    $result = $query->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);


    if (count($data) >= 1) {
        //Setze Sessionvariablen und leite weiter zu eingabe
        $_SESSION["SpielID"] = strtoupper($_POST['SpielID']);
        $_SESSION["StartedGame"] = "yes";
        header("Location: eingabe", true, 301);
    } else {
        //TODO: Fehlermeldung
    }
} else if (isset($_POST['NeuesSpiel']) && $_POST['NeuesSpiel']=='NeuesSpiel'){
    //Generate unique ID und setze sie
    $validSpielIDFound=false;
    do {
        $seed = str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ');
        $rand = '';
        foreach (array_rand($seed, 5) as $k) $rand .= $seed[$k];
        $query = $conn->prepare("SELECT * FROM session WHERE SpielID = ?");
        $query->bind_param("s",$rand);
        $query->execute();
        $result = $query->get_result();
        if ($result->num_rows == 0) {$validSpielIDFound=true;}
    } while (!$validSpielIDFound);
    
    $_SESSION["SpielID"] = $rand;
    $_SESSION["StartedGame"] = "yes";

    $query = $conn->prepare("INSERT INTO session (SpielID) VALUES (?)");
    $query->bind_param("s",$rand);
    $query->execute();
    $conn->close();
    //Redirect to eingabe
    header("Location: eingabe", true, 301);
}
?>
<!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/github-fork-ribbon-css/0.2.3/gh-fork-ribbon.min.css" />
    <link rel="stylesheet" type="text/css" href="pure-min.css" media="screen" />
    <link rel="stylesheet" type="text/css" href="style.css" media="screen" />
    <title>Startseite</title>
  </head>
  <body>
  <div id="wrapper">
    <main>
        <form id="indexfirstform" action="index" class="pure-form" method="post">
            <fieldset>
                <input type="text" name="SpielID" id="SpielID" placeholder="ABCDE"/>
                <button class="pure-button pure-button-primary" id="SpielBeitretenButton" type="submit" value="SpielBeitreten">Spiel beitreten</button>
            </fieldset>
        </form>
        <hr>
        <form id="NeuesSpielForm" action="index" class="pure-form" method="post">
            <button class="pure-button pure-button-primary pure-u-3-8" type="submit" value="NeuesSpiel" name="NeuesSpiel">Neues Spiel</button>
        </form>
    </main>
    <a class="github-fork-ribbon left-bottom pure-u-1-2" href="https://github.com/PhForty/SongGame" data-ribbon="Fork me on GitHub" title="Fork me on GitHub">
            Fork me on GitHub 
        </a>   

    </div>
  </body>
</html>