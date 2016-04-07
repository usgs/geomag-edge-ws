<?php

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

  public function getTimesArray () {
    if (count($this->traceBufs) === 0) {
      return null;
    }
    $times = array();

    $samplingRate = $this->traceBufs[0]->samplingRate;
    $len = ($this->endTime - $this->startTime) * $samplingRate;
    $delta = 1 / $samplingRate;
    for ($i = 0; $i <= $len; $i++) {
      $times[] = $this->startTime + $i * $delta;
    }

    return $times;
  }

  /**
   * Get data for a specific time.
   *
   * @param $time {Number}
   *        requested time.
   * @return {Number}
   *         data at point nearest to $time, or null if not available.
   */
  public function getData ($time) {
    $tb_len = count($this->traceBufs);
    if ($tb_len === 0) {
      // no data
      return null;
    }

    for ($tb = 0; $tb < $tb_len; $tb++) {
      $traceBuf = $this->traceBufs[$tb++];
      $samplingRate = $traceBuf->samplingRate;

      if ($time < $traceBuf->startTime) {
        // data not available
        return null;
      } else if ($time <= $traceBuf->endTime) {
        // in current tracebuf, compute index.
        $index = ($time - $traceBuf->startTime) * $samplingRate;
        $index = round($index);
        return $traceBuf->data[$index];
      }
    }

    // didn't find data
    return null;
  }

  /**
   * Merge data from tracebufs, filling gaps with null values.
   *
   * @param $startTime {Number}
   *        default null.
   *        use a specific start time.
   *        when null, use time of the first sample in response.
   * @param $endTime {Number}
   *        default null.
   *        use a specific end time.
   *        when null, use time of the last sample in response.
   * @return {Array}
   *         null if no tracebufs part of response.
   *         otherwise, one data point for all data between start and end time.
   */
  public function getDataArray ($startTime=null, $endTime=null) {
    $tb_len = count($this->traceBufs);
    if ($tb_len === 0) {
      // no data
      return null;
    }

    if ($startTime === null) {
      $startTime = $this->startTime;
    }
    if ($endTime === null) {
      $endTime = $this->endTime;
    }

    $tb = 0;
    $traceBuf = $this->traceBufs[$tb++];
    $samplingRate = $traceBuf->samplingRate;
    $delta = 1 / $samplingRate;

    $data = array();
    $len = ($endTime - $startTime) * $samplingRate;
    for ($i = 0; $i <= $len; $i++) {
      $time = $startTime + $i * $delta;

      if ($traceBuf === null) {
        // no data
        $data[] = null;
        continue;
      }

      if ($time > $traceBuf->endTime) {
        // after current tracebuf
        if ($tb === $tb_len) {
          // out of tracebufs
          $traceBuf = null;
          $data[] = null;
          continue;
        }
        $traceBuf = $this->traceBufs[$tb++];
      }

      if ($time < $traceBuf->startTime) {
        // before current tracebuf = gap
        $data[] = null;
        continue;
      }

      $index = ($time - $traceBuf->startTime) * $samplingRate;
      $index = round($index);
      $data[] = $traceBuf->data[$index];
    }

    return $data;
  }

}
