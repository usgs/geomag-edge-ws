<?php

include_once $LIB_DIR . '/classes/Timeseries.class.php';


/**
 * Parser for TraceBuf part of a waveserver response.
 */
class TraceBuf {

  protected $pin;
  public $numSamples;
  public $startTime;
  public $endTime;
  public $samplingRate;
  public $delta;
  public $station;
  public $network;
  public $channel;
  public $location;
  public $version;
  public $dataType;
  public $quality;
  protected $pad;
  public $data;


  /**
   * Parse a tracebuf.
   *
   * @param $buffer {Buffer}
   *        buffer containing tracebuf data.
   */
  public function __construct($buffer) {
    $header = $buffer->unpack('2i3d7s9s4s3s2s3s2s2s');
    $this->pin = $header[0];
    $this->numSamples = $header[1];
    $this->startTime = $header[2];
    $this->endTime = $header[3];
    $this->samplingRate = $header[4];
    if ($this->samplingRate !== 0) {
      $this->delta = 1 / $this->samplingRate;
    }
    $this->station = $header[5];
    $this->network = $header[6];
    $this->channel = $header[7];
    $this->location = $header[8];
    $this->version = $header[9];
    $this->dataType = $header[10];
    $this->quality = $header[11];
    $this->pad = $header[12];
    $this->data = $buffer->unpack($this->numSamples . 'i');
  }


  /**
   * Generate parallel arrays of data and times.
   *
   * @return {Array<'data','times' => Array>}
   */
  public function toTimeseries () {
    $data = array();
    $times = array();

    $len = count($this->data);
    for ($i = 0; $i < $len; $i++) {
      $data[] = $this->data[$i];
      $times[] = $this->startTime + ($i * $this->delta);
    }

    return new Timeseries($data, $times);
  }

  /**
   * Summarize tracebuf.
   *
   * @return {String}
   *     string including tracebuf metadata.
   */
  public function toString () {
    return implode(', ', array(
      'numSamples=' . $this->numSamples,
      'startTime=' . $this->startTime,
      'endTime=' . $this->endTime,
      'samplingRate=' . $this->samplingRate,
      'delta=' . $this->delta
    ));
  }

}
