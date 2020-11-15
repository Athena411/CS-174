<?php

require_once 'login.php';

//WEBPAGE
//connect to database called 'advisors'
$conn = new mysqli($hn, $un, $pw, $db);
$loggedIn = false;

if ($conn->connect_error) 
    die($connection->connect_error);

//From the first page, a user can sign up or log in
//If the user clicks the log in button and sucessfully logs in, then display upload form and their specific uploaded information.
if(isset($_POST['btnLogIn']))
{
    if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']))
    {
        $un_temp = mysql_entities_fix_string($conn, $_SERVER['PHP_AUTH_USER']);    
        $pw_temp = mysql_entities_fix_string($conn, $_SERVER['PHP_AUTH_PW']);    
        $query = "SELECT * FROM users WHERE username='$un_temp'";    
        $result = $conn->query($query);
        if (!$result) die("Somethihng went wrong please try again later.");
        else if($result->num_rows)
        {
            $row = $result->fetch_array(MYSQLI_NUM);
            //$result->close();
            $salt = $row[3];
            $hashedPass = hash('ripemd128', "$pw_temp$salt");
            if ($hashedPass == $row[2])
            {
                echo "Hi $row[0], you are now logged in as '$row[1]'";
                $email = getEmail($conn, $un_temp);
                $loggedIn = true;
                echo <<<_END
                <html><head><title>PHP Form Upload</title></head><body>
                <form  action='test.php' method='post' enctype='multipart/form-data'>
                <pre>
                Enter in the name of the text file you want to upload and then upload the file itself.
                    Name <input type="text" name="name">
                    Select File: <input type='file' name='filename' size='10'>
                    <input type='submit' name = 'btnUpload' value='Upload'>
                    </pre>
                </form>
                _END;
                displayUserInputs($conn, $email);
            } 
            
            else 
                die("Invalid username/password combination");
        }
    }
    else
    {
        header('WWW-Authenticate: Basic realm="Restricted Section“');
        header('HTTP/1.0 401 Unauthorized');
        die ("Please enter your username and password");
    }
}

//if a user has uploaded a file and entered a string input, store it into a user inputs database
if ($_FILES)
{
    $name = htmlentities($_FILES[('filename')]['name']);
    $ftype = htmlentities($_FILES[('filename')]['type']);
    if ($ftype == "text/plain") {
        if(!empty($_POST['name']))
        {
            $user = mysql_entities_fix_string($conn, $_SERVER['PHP_AUTH_USER']); 
            $nameInput = mysql_entities_fix_string($conn, $_POST['name']);
            $fileContent = mysql_entities_fix_string($conn, file_get_contents($name));
            $email = getEmail($conn, $user);
            storeInputAndContents($conn, $nameInput, $fileContent,$email);
        }
        else{
            echo "Please enter a text in the name section."; 
        }
    }
    else
    {
        echo "'$name' is not an accepted text file"; 
    }
}

//Only way for a user to click the button in the first place is if they have sucessfull logged in, so we know from this portion, the user if logged in.
//If the user has press the upload button, show the upload form again and the uploaded information specific to the logged in user
if(isset($_POST['btnUpload']))
{
    $user = mysql_entities_fix_string($conn, $_SERVER['PHP_AUTH_USER']); 
    $email =  getEmail($conn, $user);
    $loggedIn = true;
    echo <<<_END
    <html><head><title>PHP Form Upload</title></head><body>
    <form  action='test.php' method='post' enctype='multipart/form-data'>
    <pre>
    Enter in the name of the text file you want to upload and then upload the file itself.
        Name <input type="text" name="name">
        Select File: <input type='file' name='filename' size='10'>
        <input type='submit' name = 'btnUpload' value='Upload'>
        </pre>
    </form>
    _END;
    displayUserInputs($conn, $email);
}

//If a user created an account from filling in all of the fields, store the users information into a user account database.
if(!empty($_POST['email']) && !empty($_POST['username']) && !empty($_POST['password']))
{
    $email = mysql_entities_fix_string($conn, $_POST['email']);
    $userName = mysql_entities_fix_string($conn, $_POST['username']);
    $password = mysql_entities_fix_string($conn, $_POST['password']);
    $salt = generateSalt();
    $hashedPw = hash('ripemd128', "$password$salt");

    addUser($conn, $email, $userName, $hashedPw, $salt);
}

//SIGN UP, display sign up portion if the user has not successfully logged in yet.
if($loggedIn == false)
{
    echo <<<_END
<html><head><title>Advisor form</title></head><body>
<form  action='test.php' method='post' enctype='multipart/form-data'>
<pre>
    <b style="font-size:30px">SIGNUP FORM</b>
    Email: <input type="text" name="email">
    Username: <input type="text" name="username">
    Password: <input type="text" name="password">

                  <input type='submit' value='Sign up' style="height:30px; width:80px">
                  <input type='submit' name = 'btnLogIn' value='Log in' style='height:30px; width:80px'>
_END;
}

echo '<br>';
$conn->close();

//----------------------------------------------------------------------------------------------------------
function generateSalt()
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'; 
    $salt = ''; 
  
    for ($i = 0; $i < 10; $i++) { 
        $index = rand(0, strlen($characters) - 1); 
        $salt .= $characters[$index]; 
    } 
  
    return $salt; 
}

function addUser($conn, $email, $userName, $password, $passwordSalt)
{
    $query = "INSERT INTO users VALUES" ."('$email','$userName','$password','$passwordSalt')";
    $result = $conn->query($query);
    if (!$result)
    {
        connectionErrorMsg();
    } 
}

function mysql_entities_fix_string($connection, $string) 
{
    return htmlentities(mysql_fix_string($connection, $string));
}

function mysql_fix_string($connection, $string) 
{
    if (get_magic_quotes_gpc()) $string = stripslashes($string);
    return $connection->real_escape_string($string);
}

function getEmail($conn, $userName)
{
    $un_temp = mysql_entities_fix_string($conn, $userName);       
    $query = "SELECT * FROM users WHERE username='$un_temp'";    
    $result = $conn->query($query);
    if (!$result) die("Somethihng went wrong please try again later.");
    else if($result->num_rows)
    {
        $row = $result->fetch_array(MYSQLI_NUM);
        if(!empty($row[0]))
        {
            return $row[0];
        }
    }
    return "";
}

function displayUserInputs($conn, $email)
{
    $query = "SELECT * FROM userInputs WHERE email='$email'"; 
    $result = $conn->query($query);
    if (!$result) die("We could not retrieve information"); 
    $rows = $result->num_rows;
    for ($j = 0 ; $j < $rows ; ++$j)
    {
        $result->data_seek($j);
        $row = $result->fetch_array(MYSQLI_NUM);
        echo <<<_END
        <pre>
        Name: $row[0]
        File contents: $row[1]
        </pre>
        _END;
    }
}

function storeInputAndContents($conn,$name, $content, $email)
{
    $query = "INSERT INTO userInputs VALUES" ."('$name','$content','$email')";
    $result = $conn->query($query);
    if (!$result)
    {
        die($conn->error);
    } 
}
?>