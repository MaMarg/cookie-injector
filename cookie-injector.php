<?php
/*
* Plugin Name: Cookie Injector
* Plugin URI: https://preventicus.com
* Description: Injects only essential cookies on specific websites.
* Version: 1.0.0
* Author: Marvin Margull
* License: GPL2
*/

// === GLOBAL VARIABLES ===

$successMessage = '<div class="notice notice-success is-dismissible"> 
<p><strong>Success! </strong>Your changes have been saved.</p>
</div>';

$invalidHyperlink = '<div class="notice notice-error is-dismissible">
<p><strong>Error! </strong>Please enter an valid URL.</p>
</div>';

//=== COMMON CODE ===

function maybeCreateTable($tableName, $tableParams)
{
  global $wpdb;

  //Looks if the table already exists.
  $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tableName));

  if ($wpdb->get_var($query) === $tableName) {
    return true;
  }

  // Didn't find it, so try to create it.
  $wpdb->query('CREATE TABLE ' . $tableName . ' (
    ' . $tableParams . '
  );');

  return false;
}


function getID()  //determinies the ID of the database where the hyperlinks are   muss nicht jedes mal abgerufen werden
{
  global $wpdb;
  $findIDquery = 'SELECT ID FROM `prev_cookieinjector` ORDER BY ID ASC LIMIT 1;';
  $findID = $wpdb->get_results($findIDquery);

  if (count($findID) > 0) {
    return $findID[0]->ID;
  } else {
    return "";
  }
}


function arrayToString($array)    //converts array to string with comma
{
  $strings = "";

  foreach ($array as $key => $value) {
    if ($key + 1 == count($array)) {
      $strings .= $value;
    } else {
      $strings .= $value . ",";
    }
  }
  return $strings;
}

//=== INITIALIZATION ===

//creates a new db-table if it doesnt already exists
maybeCreateTable('prev_cookieinjector', 'ID int, Hyperlink varchar(255), DomainPath varchar(255), Expires varchar(255), UID varchar(255), Version varchar(255), PRIMARY KEY (ID)');

//=== DB QUERIES ===

function dbSelect()       //if getId != number return empty array
{
  global $wpdb;
  $result = $wpdb->get_results("SELECT Hyperlink FROM prev_cookieinjector WHERE ID = '" . getID() . "';");  //Selects hyperlinks from DB

  if (getID() === "") {
    return [];
  } else {
    if (count($result) > 0) {
      return explode(", ", $result[0]->Hyperlink);
    }
  }
}


function dbUpdateOrInsert()   //updates hyperlink(s) in db, if entry doesn't exists --> add new
{
  global $wpdb;

  if (dbSelect() === []) {
    //dbinsert

    $wpdb->insert(
      $wpdb->prefix . 'cookieinjector',
      array(
        'ID' => 1,
        'Hyperlink' => $_POST['hyperlink_input'],
        'DomainPath' => $_POST['domainPath'],
        'Expires' => $_POST['expiringDate'],
        'UID' => $_POST['uid'],
        'version' => $_POST['version'],
      ),
      array(
        '%d', //ID
        '%s',  //Hyperlink
        '%s',  //Domain Path
        '%s',  //Expires
        '%s',  //UID
        '%d'  //Version
      )
    );
  } else {
    //dbupdate

    $wpdb->update(
      $wpdb->prefix . 'cookieinjector',
      array(
        'Hyperlink' => $_POST['hyperlink_input'],
      ),
      array('ID' => getID()),
      array(
        '%s'  //String (Hyperlink)
      ),
      array('%d') //Integer (ID)
    );
  }
}


$url = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

// Append the host(domain name, ip) to the URL.   
$url .= $_SERVER['HTTP_HOST'];

// Append the requested resource location to the URL   
$url .= $_SERVER['REQUEST_URI'];

$urls = dbSelect();

foreach ($urls as $entry) {         //goes to hyperlinks in db, if the url in browser and db are the same, it executes the following script to set cookies
  if ($url == $entry) {
    //code to set Borlabs Cookie with sufficent timestamp, so Cookie Banner is avoided
    wp_enqueue_script("cookie-injector", "/wp-content/plugins/cookie-injector/set-cookiescript.js");
    break;
  }
}

//=== UI ===

add_action('admin_menu', 'wpadmin');


function wpadmin()
{
  add_menu_page('Plugin Page', 'Cookie Injector', 'manage_options', 'cookie-injector', 'adminpanel'); //Page title, menu title (side bar), function, URL Slug, Position
}

function adminpanel()
{

  function storeDB($urlString) //store url: update or create db-entry
  {
    GLOBAL $successMessage;

    try {
      dbUpdateOrInsert();
      echo $successMessage;
    } catch (Exception $error) {
      //error message
      echo '<div class="notice notice-error is-dismissible">
      <p><strong>Error! </strong>Something went wrong(' . $error->getMessage() . ')</p>
      </div>';
    }
  }


  function verifyURL()  //verifies the URL on correctness
  {
    GLOBAL $invalidHyperlink;

    $urlString = preg_replace("/<br>|\n|\r/", "", $_POST['hyperlink_input']);
    $urlArray = explode(", ", $urlString);

    for ($i = 0; $i < sizeof($urlArray); $i++) {
      if (!filter_var($urlArray[$i], FILTER_VALIDATE_URL)) {
        echo $invalidHyperlink;
        return;
      }
    }
    storeDB($urlString);    //if the URL is correct it adds/remove/changes the hyperlink
  }

  if (isset($_POST['submit-btn'])) {    //executes verifyURL() on btn-click
    verifyURL();
  }

  function submitJsParams()     //submits JS parameter
  {
    global $wpdb;

    $wpdb->update(
      $wpdb->prefix . 'cookieinjector',

      array(
        'DomainPath' => $_POST['domainPath'],
        'Expires' => $_POST['expiringDate'],
        'UID' => $_POST['uid'],
        'Version' => $_POST['version'],
      ),
      array('ID' => getID()),
      array(
        '%s',  //String (Hyperlink)
        '%s',  //String (Expires)
        '%s',  //String (UID)
        '%d'  //Integer (version)
      ),
      array('%d')     //Integer (ID)
    );
  }

  if (isset($_POST['js-submit-btn'])) {
    GLOBAL $successMessage;

    try {
      submitJsParams();
      echo $successMessage;
    } catch(Exception $error) {
      echo '<div class="notice notice-error is-dismissible">
      <p><strong>Error! </strong>Something went wrong(' . $error->getMessage() . ')</p>
      </div>';
    }
    
  }

  function selectParams($name)
  {     //selects the parameters to display it in the UI
    global $wpdb;
    $result = $wpdb->get_results("SELECT $name FROM prev_cookieinjector WHERE ID = '" . getID() . "';");

    return $result[0]->$name;
  }

  //plugin UI

  echo '<!DOCTYPE html>
  <html lang="en">
  
  <head>
      <meta charset="UTF-8">
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Plugin Page (1)</title>
      <script src="set-cookiescript.js"></script>
  </head>
  
  <body>
      <center>
          <h1 class="tracking-headline">Reject Tracking on Following Pages</h1>
  
          <form class="tracking-form" method="post">

              <textarea class="large-text code" id="largetext" name="hyperlink_input">' . arrayToString(dbSelect()) . '</textarea>
              <br>
              <br>

              <input type="submit" class="button button-primary" id="submit-btn" name="submit-btn" value="Update">
              <br>
              <br>

              <div class="tooltip">?
                  <span class="tooltiptext">
                      Enter the link you want to reject tracking in the text box and press "Update".
                      If you want to add multiple links, seperate them with a comma ( , ).
                  </span>
              </div>

          </form>
  
          <h1 class="js-headline">JavaScript Settings (Cookie Value)</h1>
  
          <form class="js-form" method="post">

              <span id="span-domain">Domain Path: </span><input type="text" class="form-control form-control-sm d-inline-block w-75 mr-2"
                  name="domainPath" value="' . selectParams('DomainPath') . '" id="domainPath"><br><br>

              <span>Expires: </span><input type="datetime-local" class="form-control form-control-sm d-inline-block w-75 mr-2"
                  name="expiringDate" value="' . selectParams('Expires') . '" id="expiringDate"><br><br>

              <span id="span-uid">UID: </span><input type="text" class="form-control form-control-sm d-inline-block w-75 mr-2"
                  name="uid" value="' . selectParams('UID') . '" id="uid"><br><br>

              <span id="span-version">Version: </span><input type="number" class="form-control form-control-sm d-inline-block w-75 mr-2"
                  name="version" value="' . selectParams('Version') . '" id="version"><br><br>

              <input type="submit" class="button button-primary" value="Update" id="submit-btn" name="js-submit-btn">

          </form>

      </center>
  
      <style>
          .tracking-headline {
              margin-top: 5rem;
          }
  
          .js-headline {
              margin-top: 8rem;
          }

          .js-form {
              margin-top: 2rem;
          }

          #domainPath {
            position: relative;
            right: 1rem;
          }

          #uid {
            position: relative;
            left: .5rem;
          }

          #span-domain {
            position: relative;
            right: 1rem;
          }

          #span-uid {
            position: relative;
            left: .5rem;
          }

          #span-version {
            position: relative;
            right: .15rem;
          }

          #version {
            position: relative;
            right: .1rem;
          }
  
          .tracking-form {
              margin-top: 2rem;
          }
  
          #submit-btn {
              height: 2.5rem;
              width: 5rem;
              font-size: 18px;
              border: 2px solid #000;
          }
  
          div.listed_pages {
              margin-top: 5rem;
          }
  
          #largetext {
              width: 35rem;
              font-family: "Arial";
          }
  
          .tooltip {
              font-size: 20px;
              position: relative;
              top: -3.75rem;
              left: 5rem;
              display: inline-block;
              color: #fff;
              background-color: #000;
              border: 2px solid #0073aa;
              border-radius: 12px;
              padding: 11px;
          }
  
          .tooltip:hover {
              background-color: #333333;
              cursor: pointer;
          }
  
          .tooltip .tooltiptext {
              font-size: 16px;
              visibility: hidden;
              width: 200px;
              background-color: #555;
              color: #fff;
              text-align: center;
              border-radius: 6px;
              padding: 12.5px;
              position: absolute;
              top: 150%;
              left: 50%;
              margin-left: -115px;
          }
  
          .tooltip:hover .tooltiptext {
              visibility: visible;
          }
      </style>
  </body>
  
  </html>';

  // readfile('/xampp_php7.4/htdocs/preventicus_com/wp-content/plugins/cookie-injector/ui1.html');
  // echo arrayToString(dbSelect());
  // readfile('/xampp_php7.4/htdocs/preventicus_com/wp-content/plugins/cookie-injector/ui2.html');
}
