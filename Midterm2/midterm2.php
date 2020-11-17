<?php

require_once 'login.php';

//WEBPAGE
//connect to database called 'advisors'
$conn = new mysqli($hn, $un, $pw, $db);
$loggedIn = false;

if ($conn->connect_error) 
    die(connectionErrorMsg());

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
        if($result->num_rows)
        {
            $row = $result->fetch_array(MYSQLI_NUM);
            $result->close();
            $salt = $row[3];
            $hashedPass = hash('ripemd128', "$pw_temp$salt");
            if ($hashedPass == $row[2])
            {
                echo "Hello you are logged in as '$row[1]'";
                $email = getEmail($conn, $un_temp);
                $loggedIn = true;
                echo <<<_END
                <html><head><title>PHP Form Upload</title></head><body>
                <form  action='midterm2.php' method='post' enctype='multipart/form-data'>
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
        else
        {
            die("Account does not exist.");
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
            echo "Please enter a text in the name section.<br>"; 
        }
    }
    else
    {
        echo "'$name' is not an accepted text file <br>"; 
    }
}

//Only way for a user to click the button in the first place is if they have sucessfull logged in, so we know from this portion, the user if logged in.
//If the user has press the upload button, show the upload form again and the uploaded information specific to the logged in user
if(isset($_POST['btnUpload']))
{
    $user = mysql_entities_fix_string($conn, $_SERVER['PHP_AUTH_USER']); 
    $email =  getEmail($conn, $user);
    $loggedIn = true;
    echo "Hello you are logged in as '$user'";
    echo <<<_END
    <html><head><title>PHP Form Upload</title></head><body>
    <form  action='midterm2.php' method='post' enctype='multipart/form-data'>
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
<form  action='midterm2.php' method='post' enctype='multipart/form-data'>
<pre>
    <b style="font-size:30px">SIGNUP FORM</b>
    Email:    <input type="text" name="email">
    Username: <input type="text" name="username">
    Password: <input type="text" name="password">

                  <input type='submit' value='Sign up' style="height:30px; width:80px">

                  <input type='submit' name = 'btnLogIn' value='Log in' style='height:30px; width:80px'>
_END;
}
$conn->close();
//----------------------------------------------------------------------------------------------------------

/**
 * Function to output an error message if a connection or query fails.
 */
function connectionErrorMsg()
{
    echo "Sorry we could not fufill your request, for there was an error. Please refresh the page and try again or contact us at admin@sjsu.edu";
}

/**
 * Generate a random salt of size 10 consisting of characters and numbers.
 * @return salt: a random string that will be concatenated to the users password and then hashed to be stored into a database.
 */
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

/**
 * Create an account from users email, username, password inputs along with a generated salt for that specific user.
 * @param conn database connection object
 * @param email email of the user
 * @param userName username that the user chose
 * @param password password that the user chose
 * @param passwordSalt random generated string to be acted as a salt for the specific user.
 */
function addUser($conn, $email, $userName, $password, $passwordSalt)
{
    $stmt = $conn->prepare('INSERT INTO users VALUES(?,?,?,?)');
    $stmt->bind_param('ssss', $em, $un, $pwd, $salt);
    $em = $email;
    $un = $userName;
    $pwd = $password;
    $salt = $passwordSalt;
    if($stmt->execute())
    {
        echo "Sign up successful.";
    }
    else
    {
        echo "Sign up unsuccessful.";
    }
    $stmt->close();
}


/**
 * Another sanitization string function, but converting strings that may have html tags
 * @return htmlentities: converted string to make strings with html tags safer.
 */
function mysql_entities_fix_string($connection, $string) 
{
    return htmlentities(mysql_fix_string($connection, $string));
}

/**
 * Sanitize any string from quotes;
 * @return strippedQuotes: a sanitized version of a string mainly stripped from quotes if it originally had any.
 */
function mysql_fix_string($connection, $string) 
{
    if (get_magic_quotes_gpc()) $string = stripslashes($string);
    return $connection->real_escape_string($string);
}

/**
 * After the user logs in, the application will need to retrieve the user's email to access their uploaded content.
 * @param conn database connection object
 * @param userName username of the logged in user
 */
function getEmail($conn, $userName)
{
    $un_temp = mysql_entities_fix_string($conn, $userName);       
    $query = "SELECT * FROM users WHERE username='$un_temp'";    
    $result = $conn->query($query);
    if (!$result) die(connectionErrorMsg());
    else if($result->num_rows)
    {
        $row = $result->fetch_array(MYSQLI_NUM);
        if(!empty($row[0]))
        {
            return $row[0];
        }
    }
    $result->close();
    return "";
}

/**
 * Display data from the userInput database based the email of the logged in user.
 * @param conn database connection object
 * @param email email of the logged in user
 */
function displayUserInputs($conn, $email)
{
    $query = "SELECT * FROM userInputs WHERE email='$email'"; 
    $result = $conn->query($query);
    if (!$result) die(connectionErrorMsg()); 
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
    $result->close();
}

/**
 * Store users' string input and file content into userInputs database
 * @param conn database connection object
 * @param name users string input
 * @param content uploaded file content
 */
function storeInputAndContents($conn,$name, $content, $email)
{
    $stmt = $conn->prepare('INSERT INTO userInputs VALUES(?,?,?)');
    $stmt->bind_param('sss', $nm, $cont, $em);
    $nm = $name;
    $cont = $content;
    $em = $email;
    if($stmt->execute())
    {
        echo "Upload successful <br>";
    }
    else
    {
        connectionErrorMsg();
    }
    $stmt->close();
}
?>