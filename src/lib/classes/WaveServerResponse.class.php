<?php

include_once $LIB_DIR . '/classes/Timeseries.class.php';


/**
 * Parser for first line of a response from a GETSNCLRAW request.
 *
 * Does not parse TraceBufs from response, but they are stored on this object.
 */
class WaveServerResponse {

  protected $requestId;
  protected $pin;
  public $station;
  public $channel;
  public $network;
  public $location;
  public $flag;
  public $dataType;
  public $startTime;
  public $endTime;
  public $numBytes;

  public $traceBufs;

  /**
   * Parse a waveserver response.
   *
   * @param $line {String}
   *        first line from GETSNCLRAW response.
   */
  public function __construct($line) {
    $parts = explode(' ', $line);
    $this->requestId = $parts[0];
    $this->pin = $parts[1];
    $this->station = $parts[2];
    $this->channel = $parts[3];
    $this->network = $parts[4];
    $this->location = $parts[5];
    $this->flag = $parts[6];
    $this->dataType = $parts[7];
    $this->startTime = null;
    $this->endTime = null;
    $this->numBytes = 0;
    $this->traceBufs = array();

    if ($this->flag === 'F') {
      $this->startTime = intval($parts[8]);
      $this->endTime = intval($parts[9]);
      $this->numBytes = intval($parts[10]);
    } else if ($this->flag === 'FL') {
      $this->startTime = intval($parts[8]);
    } else if ($this->flag === 'FR') {
      $this->endTime = intval($parts[8]);
    }
  }

  /**
   * Generate parallel arrays of data and times.
   *
   * @return {Array<'data','times' => Array>}
   */
  public function toTimeseries () {
    $timeseries = new Timeseries();

    for ($tb = 0, $tb_len = count($this->traceBufs); $tb < $tb_len; $tb++) {
      $traceBuf = $this->traceBufs[$tb];

      $timeseries = $timeseries->concat($traceBuf->toTimeseries());
    }

    return $timeseries;
  }

  /**
   * Summarize tracebuf.
   *
   * @return {String}
   *     string including tracebuf metadata.
   */
  public function toString () {
    return implode(', ',
        array(
          'request=' . $this->requestId,
          'pin=' . $this->pin,
          'sncl=' . implode('|', array(
              $this->station,
              $this->network,
              $this->channel,
              $this->location)),
          'flag=' . $this->flag,
          'dataType=' . $this->dataType,
          'startTime=' . $this->startTime,
          'endTime=' . $this->endTime,
          'numBytes=' . $this->numBytes,
          'traceBufs=' . count($this->traceBufs))
        ) . "\n\t" .
        implode("\n\t", array_map(function ($tb) {
          return $tb->toString();
        }, $this->traceBufs));
  }

}
