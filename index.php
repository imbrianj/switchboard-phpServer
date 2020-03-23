<?php
  /**
   * Copyright (c) 2014 brian@bevey.org
   *
   * Permission is hereby granted, free of charge, to any person obtaining a copy
   * of this software and associated documentation files (the "Software"), to
   * deal in the Software without restriction, including without limitation the
   * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
   * sell copies of the Software, and to permit persons to whom the Software is
   * furnished to do so, subject to the following conditions:
   *
   * The above copyright notice and this permission notice shall be included in
   * all copies or substantial portions of the Software.
   *
   * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
   * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
   * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
   * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
   * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
   * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
   * IN THE SOFTWARE.
   */

  /**
   * @author brian@bevey.org
   * @fileoverview Accepts a POST or GET requests of device state data and
   *               logs to a protected local file that may be recalled with
   *               correct credentials.
   * @note This was built to have Tasker send GPS coordinates based on my
   *       movement while connected to my car's Bluetooth.
   *       * From your Android phone, install Tasker:
   *         https://play.google.com/store/apps/details?id=net.dinglisch.android.taskerm
   *       * In "Profiles" hit "+" -> "State" -> "Net" -> "BT Connected" ->
   *         and enter your car's Bluetooth name or address.
   *       * Assign this Profile to a Task: "Variables" -> "Variable Set" ->
   *         and enter "Name" as "%Car" and "To" to "true".
   *       * Similarly, you'll need to create a "Profile" for "Out of the car"
   *         that will assign the "%Car" variable to "false" following these
   *         same steps.
   *       * Now that Tasker knows when you're in the car, you can log your
   *         location by:
   *       * "Profile" -> "+" -> "State" -> "Variables" -> "Variable Value" ->
   *         "%Car" "Equals" "true"
   *       * Assign this to a "Task" called "Ping Location":
   *       * 1. "+" -> "Net" -> "HTTP Post".  Enter your host, port, path.
   *            Be sure your "Path" is suffixed with a trailing "/".
   *            Set "Data / File" to:
   *            user=[YOURNAME]&pass=[YOURPASS]&type=location&latlong=%LOC&altitude=%LOCALT&speed=%LOCSPD
   *       * 2. "+" -> "Task" -> "Wait" -> 5 minutes.
   *       * 3. "+" -> "Task" -> "Goto" -> Action #1
   *       Your car should now submit it's location, speed and altitude
   *       information every three minutes while driving - and be able to be
   *       recalled by anyone that has your credentials.
   * @note For use with a GQ Geiger Counter,
   *       * Connect to WiFi: Power -> WiFi -> SSID -> [enter your SSID];
   *         Password -> [enter your WiFi password].  You should then see a
   *         confirmation that WiFi "Connected!"
   *       * Connect to Server: Power -> Server -> Website [enter your domain];
   *         URL -> [enter the reference to this filename]
   *         User ID -> [enter the "password" to be used for the "geiger" user]
   */

class request {
  // You'll need to edit this to include the credentialed users.
  protected $users = array('user' => 'password', 'geiger' => '0123456789');
  protected $maxCount;
  protected $user;
  protected $data;
  protected $dataType;
  protected $dataTypes = array('location', 'geiger');

  public function request () {
    $output       = '';
    $credentialed = false;
    $hasData      = false;
    $user         = '';
    $pass         = '';
    $count        = 0;
    $maxCount     = 10;

    // You need to have a user and password to do anything.
    if((array_key_exists('user', $_POST) && array_key_exists('pass', $_POST)) ||
       (array_key_exists('AID', $_GET))) {
      // Expected inputs - sanitized
      $user     = filter_var($_POST['user'],    FILTER_SANITIZE_STRING); // "test"
      $pass     = filter_var($_POST['pass'],    FILTER_SANITIZE_STRING); // "pass"
      $count    = filter_var($_POST['count'],   FILTER_SANITIZE_NUMBER_INT);
      $dataType = in_array($_POST['type'], $this->dataTypes, true) ? $_POST['type'] : null;

      // Some devices (geiger) cannot handle POST, so we'll fall-back to GET.
      if(!$user && !$pass && $_GET['AID']) {
        $user     = 'geiger';
        $pass     = filter_var($_GET['AID'], FILTER_SANITIZE_STRING); // "0123456789"
        $count    = 1000;
        $dataType = 'geiger';
      }

      // Check that we have credentials and that they match.
      $credentialed = (($user && $pass) && ($this->users[$user]) && ($this->users[$user] === $pass));

      $this->user     = $user;
      $this->pass     = $pass;
      $this->maxCount = $count ? $count : $maxCount;
      $this->dataType = $dataType;

      if($credentialed) {
        if($dataType) {
          // Determine if you're looking at location or geiger
          if($this->getData()) {
            $output = $this->receive();
          }

          else {
            $output = $this->respond();
          }
        } else {
          $output = '{"err":"unknown type defined"}';
        }
      } else {
        $output = '{"err":"invalid credentials"}';
      }
    } else {
      $output = '{"err":"no credentials"}';
    }

    header('Content-Type: application/json');
    echo $output;
  }

  public function getData () {
    switch ($this->dataType) {
      case 'location' :
        return $this->location();
      break;

      case 'geiger' :
        return $this->geiger();
      break;

      default :
        return null;
    }
  }

  public function location () {
    $data = array();

    if(array_key_exists('latlong', $_POST)) {
      $latlong       = explode(',', $_POST['latlong']); // "44.44444444,-118.11111111"
      $data['lat']   = filter_var($latlong[0],                   FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
      $data['long']  = filter_var($latlong[1],                   FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
      $data['link']  = 'https://maps.google.com/?q=' . $data['lat'] . ',' . $data['long'];
    }

    if(array_key_exists('altitude', $_POST)) {
      $data['alt']   = filter_var($_POST['altitude'],            FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION); // -7.0
    }

    if(array_key_exists('speed', $_POST)) {
      $data['speed'] = number_format(filter_var($_POST['speed'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION), 2); // 0.04444444
    }

    $data['user'] = $this->user;
    $data['time'] = time();

    $this->data = ($data['lat'] && $data['long']) ? $data : null;

    return $this->data;
  }

  public function geiger () {
    $data = array();

    if(array_key_exists('GID', $_GET)) {
      $data['gid']  = filter_var($_GET['GID'],  FILTER_SANITIZE_STRING); // 0034021
    }

    if(array_key_exists('CPM', $_GET)) {
      $data['cpm']  = filter_var($_GET['CPM'],  FILTER_SANITIZE_NUMBER_INT); // 15
    }

    if(array_key_exists('ACPM', $_GET)) {
      $data['acpm'] = filter_var($_GET['ACPM'], FILTER_SANITIZE_NUMBER_INT); // 15
    }

    if(array_key_exists('uSV', $_GET)) {
      $data['usv']  = filter_var($_GET['uSV'],  FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION); // 0.075
    }

    $data['user'] = $this->user;
    $data['time'] = time();

    $this->data = $data['cpm'] ? $data : null;

    return $this->data;
  }

  public function receive () {
    $filename = $this->dataType . '/' . $this->user . '.json';
    $value = json_decode($this->respond());

    array_unshift($value, $this->data);

    if(count($value) > $this->maxCount) {
      array_pop($value);
    }

    $value = json_encode($value);

    file_put_contents($filename, $value);

    return json_encode($value);
  }

  public function respond () {
    $filename = $this->dataType . '/' . $this->user . '.json';
    $value    = '[]'; // JSON encoded array

    if(file_exists($filename)) {
      $rawValue = file_get_contents($filename);

      $value = array_slice(json_decode($rawValue), 0, $this->maxCount);

      $value = json_encode($value);
    }

    return $value;
  }
}

new request;
