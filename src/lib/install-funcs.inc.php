<?php
  /**
   * Prompts user for a configuration $option and returns the resulting input.
   *
   * @param $option {String}
   *      The name of the option to configure.
   * @param $default {String} Optional, default: <none>
   *      The default value to use if no answer is given.
   * @param $comment {String} Optional, default: $option
   *      Help text used when prompting the user. Also used as a comment in
   *      the configuration file.
   * @param $secure {Boolean} Optional, default: false
   *      True if user input should not be echo'd back to the screen as it
   *      is entered. Useful for passwords.
   * @param $unknown {Boolean} Optional, default: false
   *      True if the configuration option is not a well-known option and
   *      a warning should be printed.
   *
   * @return {String}
   *      The configured value for the requested option.
   */
  function configure ($option, $default=null, $comment='', $secure=false,
      $unknown=false) {
    // check if windows
    static $isWindows = null;
    if ($isWindows === null) {
      $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    }

    if ($unknown) {
      // Warn user about an unknown configuration option being used.
      print "\nThis next option ($option) is an unknown configuration" .
          " option, which may mean it has been deprecated or removed.\n\n";
    }

    // Make sure we have good values for I/O.
    $help = ($comment !== null && $comment !== '') ? $comment : $option;

    // Prompt for and read the configuration option value
    printf("%s [%s]: ", $help, ($default === null ? '<none>' : $default));
    if ($secure && !$isWindows) {system('stty -echo');}
    $value = trim(fgets(STDIN));
    if ($secure && !$isWindows) {system('stty echo'); print "\n";}

    // Check the input
    if ($value === '' && $default !== null) {
      $value = $default;
    }

    // Always return the value
    return $value;
  }

  /**
   * Checks if the given response seems to be in the affirmative.
   *
   * @param response {String}
   *        The input response.
   * @return True if the response seems to be affirmative. False otherwise.
   */
  function responseIsAffirmative ($response) {
    return ($response === 'Y' || $response === 'y' || $response === 'yes' ||
        $response === 'Yes' || $response === 'YES');
  }

  // NB: These functions aren't very optimized but they get called only by
  //     an otherwise already very time-consuming process, so a few milliseconds
  //     won't matter here.

  function globDirs ($directory) {
    $dirs = glob($directory . DIRECTORY_SEPARATOR . '*',
        GLOB_ONLYDIR | GLOB_NOSORT);
    $alldirs = array($directory);

    foreach ($dirs as $dir) {
      $alldirs[] = $dir;
      $alldirs = array_merge($alldirs, globDirs($dir));
    }

    return array_unique($alldirs);
  }

  function recursiveGlob ($basedir, $pattern, $flags = 0) {
    $dirs = globDirs($basedir);
    $files = array();

    foreach ($dirs as $dir) {
      $files = array_merge($files,
          glob($dir . DIRECTORY_SEPARATOR . $pattern, $flags));
    }

    return array_unique($files);
  }

  function safefloatval($value=null) {
    if ($value === null) {
      return null;
    } else {
      return floatval($value);
    }
  }

  function safeintval($value=null) {
    if ($value === null) {
      return null;
    } else {
      return intval($value);
    }
  }


  // UTILITY FUNCTIONS
  /**
   * Prompt user with a yes or no question.
   *
   * @param $prompt {String}
   *        yes or no question, should include question mark if desired.
   * @param $default {Boolean}
   *        default null (user must enter y or n).
   *        true for yes to be default answer, false for no.
   *        default answer is used when user presses enter with no other input.
   * @return {Boolean} true if user entered yes, false if user entered no.
   */
  function promptYesNo ($prompt='Yes or no?', $default=null) {
    $question = $prompt . ' [' .
        ($default === true ? 'Y' : 'y') . '/' .
        ($default === false ? 'N' : 'n') . ']: ';
    $answer = null;
    while ($answer === null) {
      echo $question;
      $answer = strtoupper(trim(fgets(STDIN)));
      if ($answer === '') {
        if ($default === true) {
          $answer = 'Y';
        } else if ($default === false) {
          $answer = 'N';
        }
      }
      if ($answer !== 'Y' && $answer !== 'N') {
        $answer = null;
        echo PHP_EOL;
      }
    }
    return ($answer === 'Y');
  }

  /**
   * Download a URL into a file.
   *
   * @param $source {String}
   *        url to download.
   * @param $dest {String}
   *        path to destination.
   * @param $showProgress {Boolean}
   *        default true.
   *        output progress to STDERR.
   * @return {Boolean} false if $dest already exists, true if created.
   */
  function downloadURL ($source, $dest, $showProgress=true) {
    if (file_exists($dest)) {
      return false;
    }
    if ($showProgress) {
      echo 'Downloading "' . $source . '"' . PHP_EOL;
    }
    $curl = curl_init();
    $file = fopen($dest, 'wb');
    curl_setopt_array($curl, array(
        CURLOPT_URL => $source,
        // write output to file
        CURLOPT_FILE => $file,
        // follow redirects
        CURLOPT_FOLLOWLOCATION => 1,
        // show progress
        CURLOPT_NOPROGRESS => ($showProgress ? 0 : 1)));
    curl_exec($curl);
    $errno = curl_errno($curl);
    curl_close($curl);
    fclose($file);
    if ($errno) {
      unlink($dest);
      throw new Exception('Unable to download, errno=' . $errno .
          ' (' . curl_strerror($errno) . ')');
    }
    return true;
  }

  /**
   * Extract a gzip compressed tar file.
   *
   * @param $file {String}
   *        path to compressed tar file.
   * @param $dest {String}
   *        path to extract files into.
   * @param $removeOriginal {Boolean}
   *        default true.
   *        remove the original file after extraction.
   */
  function extractTarGz ($file, $dest=null, $removeOriginal=true) {
    $tar = str_replace('.gz', '', $file);
    if ($dest === null) {
      $dest = str_replace('.tar', '', $tar);
    }
    // decompress to tar file
    $gzin = gzopen($file, 'rb');
    $tarout = fopen($tar, 'wb');
    while ($data = gzread($gzin, 1024)) {
      fwrite($tarout, $data);
    }
    fclose($gzin);
    fclose($tarout);
    // extract tar file
    $phar = new PharData($tar);
    $phar->extractTo($dest);
    // cleanup
    unlink($tar);
    if ($removeOriginal) {
      unlink($file);
    }
  }


  /**
   * Extract a zip compressed file.
   *
   * @param $file {String}
   *        path to compressed zip file.
   * @param $dest {String}
   *        path to extract files into.
   * @param $removeOriginal {Boolean}
   *        default true.
   *        remove the original file after extraction.
   */
  function extractZip ($file, $dest=null, $removeOriginal=true) {
    if ($dest === null) {
      $dest = str_replace('.zip', '', $file);
    }
    $zip = new ZipArchive();
    // extract zip file
    if ($zip->open($file) === TRUE) {
      $zip->extractTo($dest);
      $zip->close();
    }
    // cleanup
    if ($removeOriginal) {
      unlink($file);
    }
  }

  /**
   * Remove '#' comments in a geoname file without flattening the structure
   */
  function replaceComments ($file) {
    $data = '';
    $handle = @fopen($file, "r");
    if ($handle) {
      while (($buffer = fgets($handle, 4096)) !== false) {
        if (!preg_match('/^#/', $buffer)) {
          $data .= $buffer;
        }
      }
      if (!feof($handle)) {
          echo "Error: unexpected fgets() fail\n";
      }
      fclose($handle);
    }

    file_put_contents($file, $data);
  }

?>
