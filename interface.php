<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'storedinfo.php';


// ******************************** USER INPUT REQUIRED HERE **********************************

$mysqli = new mysqli('oniddb.cws.oregonstate.edu', 'choiy-db', $mypassword, 'choiy-db');

// ********************************************************************************************


//$mysqli = new mysqli('127.0.0.1', 'root', $mypassword, 'mydb');
if ($mysqli->connect_errno) {
    echo 'Failed to connect to MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_errno . '<br>';
    exit();
}


$table = 'videos';
$tblShown = false;


if (!mysqli_num_rows($mysqli->query("SHOW TABLES LIKE '$table'"))) {
    if (!$mysqli->query("CREATE TABLE $table(id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL UNIQUE,
                        category VARCHAR(255) NOT NULL, length INT NOT NULL, rented BOOLEAN DEFAULT 0)"))
        echo 'Table creation failed: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_errno . '<br>';
}

//displayTable($mysqli, $table, NULL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnAddVideo'])) {
    $input_valid = true;
    
    if (!isset($_POST['name']) || trim($_POST['name']) == '') {
        echo 'Enter a video name.<br>';
        $input_valid = false;
    }
    if (!isset($_POST['category']) || trim($_POST['category']) == '') {
        echo 'Enter a video category.<br>';
        $input_valid = false;
    }   
    if (!isset($_POST['length']) || trim($_POST['length']) == '' || !isint_ref($_POST['length']) || intval($_POST['length']) < 1) {
        echo 'Enter a positive integer for video length (min).<br>';
        $input_valid = false;
    }

    if ($input_valid) {    
        $name = $_POST['name'];
        
        if (mysqli_num_rows($mysqli->query("SELECT name FROM $table WHERE name = '$name'")))
            echo "$name you entered already exists. No need to add it.<br><br>";
        else {
            $cate = $_POST['category'];
            $len = $_POST['length'];
            if (!$mysqli->query("INSERT INTO $table (name, category, length) VALUES ('$name', '$cate', $len)"))
                echo 'Insert failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        }
        //displayTable($mysqli, $table, NULL);
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnDeleteVideo'])) {
    $name = $_POST['btnDeleteVideo'];
    $name = str_replace('_', ' ', $name);
    
    if (!$mysqli->query("DELETE FROM $table WHERE name = '$name'"))
        echo 'Delete failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
    //displayTable($mysqli, $table, NULL);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnCheckInOutVideo'])) {
    $name = $_POST['btnCheckInOutVideo'];
    $name = str_replace('_', ' ', $name);
    
    if (mysqli_num_rows($mysqli->query("SELECT name FROM $table WHERE name = '$name' AND rented = 0"))) // available => not checked out
        $mysqli->query("UPDATE $table SET rented = 1 WHERE name = '$name'");
    else // not available => checked out
        $mysqli->query("UPDATE $table SET rented = 0 WHERE name = '$name'");
    //displayTable($mysqli, $table, NULL);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnDeleteAllVideo'])) {
    if (mysqli_num_rows($mysqli->query("SELECT name FROM $table"))) {
        $mysqli->query("TRUNCATE TABLE $table"); // delete all rows
        $mysqli->query("ALTER TABLE $table AUTO_INCREMENT = 1"); // reset 'id' to 1
    }
    //displayTable($mysqli, $table, NULL);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnFilterMovies'])) {
    $cate = $_POST['dropdown_category'];
    displayTable($mysqli, $table, $cate);
    
    $tblShown = true;
}


function displayTable(&$mysqli, &$table, $filterCate) {
    if (!mysqli_num_rows($mysqli->query("SELECT id FROM $table"))) {
        echo '<p>No videos to display...</p>';
        return;
    }
    
    $stmt = NULL;
    if ($filterCate == NULL || $filterCate == 'all_movies')
        $stmt = $mysqli->prepare("SELECT name, category, length, rented FROM $table ORDER BY category, name");
    else { // filter by category
        if (!mysqli_num_rows($mysqli->query("SELECT id FROM $table WHERE category != '$filterCate'"))) {
            echo '<p>Videos of the selected categories do not exist. No videos to display...</p>';
            return;
        }
        $stmt = $mysqli->prepare("SELECT name, category, length, rented FROM $table WHERE category = '$filterCate' ORDER BY category, name");
    }
        
    if (!$stmt->execute()) {
        echo 'Query failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        return;
    }
    
    $res_name = NULL;
    $res_cate = NULL;
    $res_len = NULL;
    $res_rent = NULL;
    
    if (!$stmt->bind_result($res_name, $res_cate, $res_len, $res_rent)) {
        echo 'Binding output parameters failed: (' . $stmt->errno . ') ' . $stmt->error . '<br>';
        return;
    }
    
    echo '<table border="1" <tr><td bgcolor="Navy"><font color="Khaki"><b>Name</b></font>
            </td><td bgcolor="LightPink"><b>Category</b></td>
            <td bgcolor="#00CED1"><b>Length</b></td><td bgcolor="Orange"><b>Rented</b></td>
            <td bgcolor="#00FF00"><b>Delete</b></td><td bgcolor="#00FF00"><b>Status</b></td></tr>';
    
    while ($stmt->fetch()) {
        echo "<tr><td bgcolor='Navy'><font color='Khaki'>$res_name</font></td><td bgcolor='LightPink'>$res_cate</td>
            <td align='right' bgcolor='#00CED1'>$res_len</td>";
        if ($res_rent)
            echo "<td bgcolor='Orange'>checked out</td>";
        else
            echo "<td bgcolor='Orange'>available</td>";
            
        // $_POST['$res_name'] contains only the first part of the string if it has a space
        // need to prevent the string from being separated by a space in it
        $res_name = str_replace(' ', '_', $res_name);
        echo "<td bgcolor='#00FF00'><form action='interface.php' method='post'>
                    <button name='btnDeleteVideo' value=$res_name>Delete</button>
                </form></td>
                <td bgcolor='#00FF00'><form action='interface.php' method='post'>
                    <button name='btnCheckInOutVideo' value=$res_name>In/Out</button>
                </form></td>
            </tr>";
    }
    echo '</table><br>';
}


function displayMovieCategory(&$mysqli, &$table) {
    if (!mysqli_num_rows($mysqli->query("SELECT name FROM $table"))) {
        //echo '<p>No movie categories to display...</p>';
        return;
    }
    
    $stmt = $mysqli->prepare("SELECT category FROM $table GROUP BY category ORDER BY category");
    if (!$stmt->execute()) {
        echo 'Query failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        return;
    }
    
    $res_cate = NULL;
    
    if (!$stmt->bind_result($res_cate)) {
        echo 'Binding output parameters failed: (' . $stmt->errno . ') ' . $stmt->error . '<br>';
        return;
    }
    
    echo '&nbsp&nbsp<select name="dropdown_category">';
    while ($stmt->fetch())
        echo "<option value='$res_cate'>$res_cate</option>";
    echo "<option value='all_movies'>All Movies</option>";
    echo '</select>';
    echo "&nbsp&nbsp<button name='btnFilterMovies' value='filterMovies'>Filter Movies</button>";
}


function isint_ref(&$val) {
    $isint = false;
    if (is_numeric($val)) {
        if (strpos($val, '.')) {
            $diff = floatval($val) - intval($val);
            if ($diff > 0)
                $isint = false;
            else {
                $val = intval($val);
                $isint = true;
            }
        }
        else
            $isint = true;
    }   
    return $isint;
}


if (!$tblShown) {
    displayTable($mysqli, $table, NULL);
    $tblShown = true;
}


echo '<!DOCTYPE html> 
    <html lang="en">
    <head>
        <meta charset="utf-8"/>
        <title>Database Interface</title>
    </head>
    <body>';
    
echo "<form action='interface.php' method='post'>
        <fieldset>
            <legend>Add a video</legend>
            Name: <input type='text' name='name'/><br>
            Category: <input type='text' name='category'/><br>
            Length (min): <input type='number' name='length'/>&nbsp&nbsp
            <input type='submit' name='btnAddVideo' value='Add Video'/>
        </fieldset>
        <br>
        <button name='btnDeleteAllVideo' value='deleteAllVideo'>Delete All Videos</button>&nbsp";

displayMovieCategory($mysqli, $table);

echo '</form>
    </body>
    </html>';

mysqli_close($mysqli);
?>