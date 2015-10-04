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
   * @fileoverview Accepts a POST request of GPS coordinates, speed, etc and
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
   *            user=[YOURNAME]&pass=[YOURPASS]&latlong=%LOC&altitude=%LOCALT&speed=%LOCSPD
   *       * 2. "+" -> "Task" -> "Wait" -> 3 minutes.
   *       * 3. "+" -> "Task" -> "Goto" -> Action #1
   *       Your car should now submit it's location, speed and altitude
   *       information every three minutes while driving - and be able to be
   *       recalled by anyone that has your credentials.
   */

class location {
  // You'll need to edit this to include the credentialed users.
  protected $users    = array('user' => 'password');
  protected $locCount = 10;
  protected $user;
  protected $location;

  public function location () {
    $output    = '';
    $user      = '';
    $pass      = '';
    $latitude  = '';
    $longitude = '';
    $altitude  = '';
    $speed     = '';

    // You need to have a user and password to do anything.
    if(array_key_exists('user', $_POST) && array_key_exists('pass', $_POST)) {
      // Expected inputs - sanitized
      $user        = filter_var($_POST['user'],     FILTER_SANITIZE_STRING); // "test"
      $pass        = filter_var($_POST['pass'],     FILTER_SANITIZE_STRING); // "pass"

      if(array_key_exists('latlong', $_POST)) {
        $latlong   = explode(',', $_POST['latlong']); // "44.44444444,-118.11111111"
        $latitude  = filter_var($latlong[0],        FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $longitude = filter_var($latlong[1],        FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
      }

      if(array_key_exists('altitude', $_POST)) {
        $altitude  = filter_var($_POST['altitude'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION); // -7.0
      }

      if(array_key_exists('speed', $_POST)) {
        $speed     = filter_var($_POST['speed'],    FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION); // 0.04444444
      }
    }

    // Check that we have credentials and that they match.
    $credentialed = (($user && $pass) && ($this->users[$user] === $pass));
    // Distinguish between someone writing a location and one polling for one.
    $hasLocation  = ($latitude && $longitude);

    $this->user = $user;
    $this->pass = $pass;

    $this->location = array('lat'   => $latitude,
                            'long'  => $longitude,
                            'alt'   => $altitude,
                            'speed' => $speed,
                            'user'  => $user,
                            'time'  => time());

    if($credentialed) {
      if($hasLocation) {
        $output = $this->receive();
      }

      else {
        $output = $this->respond();
      }
    }

    else {
      $output = '{"err":"invalid credentials"}';
    }

    header('Content-Type: application/json');
    echo $output;
  }

  public function receive () {
    $value = json_decode($this->respond());

    array_unshift($value, $this->location);

    if(count($value) > $this->locCount) {
      array_pop($value);
    }

    $value = json_encode($value);

    file_put_contents('locations/' . $this->user . '.json', $value);

    return json_encode($value);
  }

  public function respond () {
    $filename = 'locations/' . $this->user . '.json';
    $value    = '[]'; // JSON encoded array

    if(file_exists($filename)) {
      $value = file_get_contents($filename);
    }

    return $value;
  }
}

new location;
