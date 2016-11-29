<?php


/**
 * Utility class for timeseries data.
 *
 * When $data or $times are null, empty arrays are used.
 *
 * @param $data {Array}
 *     default null.
 * @param $times {Array}
 *     default null.
 * @throws {Exception} if $data and $times are not the same length.
 */
class Timeseries {

  public $data;
  public $times;

  public function __construct ($data=null, $times=null) {
    if ($data == null) {
      $data = array();
    }
    if ($times == null) {
      $times = array();
    }

    if (count($data) != count($times)) {
      throw new Exception('data and time array lengths differ');
    }

    $this->data = $data;
    $this->times = $times;
  }


  /**
   * Concatenate two Timeseries and return a new object.
   *
   * @param $that {Timeseries}
   *     timeseries to append.
   */
  public function concat ($that) {
    return new Timeseries(
        array_merge($this->data, $that->data),
        array_merge($this->times, $that->times));
  }

  /**
   * Find the smallest distance between samples.
   *
   * @return {Number}
   *     minimum difference between time samples,
   *     or null if timeseries is empty.
   */
  public function estimateDelta () {
    $estimatedDelta = null;
    $lastTime = null;

    $len = count($this->data);
    for ($i = 0; $i < $len; $i++) {
      $time = $this->times[$i];

      if ($lastTime != null) {
        $delta = $time - $lastTime;
        if ($estimatedDelta == null || $delta < $estimatedDelta) {
          $estimatedDelta = $delta;
        }
      }

      $lastTime = $time;
    }

    return $estimatedDelta;
  }

  /**
   * Extend or trim timeseries to specified interval.
   *
   * @param $startTime {Number}
   *     float epoch timestamp.
   * @param $endTime {Number}
   *     float epoch timestamp.
   * @param $delta {Number}
   *     default null.
   *     when null, $delta is estimated using estimateDelta().
   *     NOTE: this method does not resample data.
   * @return {Timeseries}
   *     new Timeseries object set to start/end extent.
   * @see fillGaps().
   */
  public function extend ($startTime, $endTime, $delta = null) {
    $size = count($this->times);
    $timeseries = new Timeseries();

    if ($size == 0) {
      throw new Exception('cannot extend empty timeseries' .
          ', use Timeseries::generateEmpty');
    }

    $delta = ($delta == null ? $this->estimateDelta() : $delta);
    $i = 0;
    $time = $this->times[0];

    // ignore data before start
    while ($time < $startTime) {
      $i = $i + 1;
      $time = $this->times[$i];
    }

    // leading gap, will be filled by fillGaps
    if (($time - $startTime) >= $delta) {
      // set empty value in sync with remaining timeseries
      // fillGaps handles the rest
      $startTime = $time - (intval(($time - $startTime) / $delta) * $delta);
      $timeseries->times[] = $startTime;
      $timeseries->data[] = null;
    }

    // copy data in range
    while ($i < $size && $time < $endTime) {
      $time = $this->times[$i];
      $timeseries->data[] = $this->data[$i];
      $timeseries->times[] = $time;
      $i = $i + 1;
    }

    // trailing gap, will be filled by fillGaps
    if (($endTime - $time) >= $delta) {
      $endTime = $time + (intval(($endTime - $time) / $delta) * $delta);
      $timeseries->times[] = $endTime;
      $timeseries->data[] = null;
    }

    return $timeseries->fillGaps(null, $delta);
  }

  /**
   * Fill empty values.
   *
   * @param $emptyValue {Number}
   *     default null.
   * @return {Timeseries}
   *     new Timeseries object with gaps filled.
   */
  public function fillGaps ($emptyValue = null, $delta = null) {
    $size = count($this->times);
    $timeseries = new Timeseries();

    if ($size == 0) {
      return $timeseries;
    }

    $delta = ($delta == null ? $this->estimateDelta() : $delta);
    $endTime = $this->times[$size - 1];
    $gaps = $this->getTimeGaps($delta);
    $startTime = $this->times[0];

    $i = 0;
    $time = $startTime;
    foreach ($gaps as $gap) {

      // time of first missing sample
      $gapStart = $gap[0] + $delta;
      // time of last missing sample
      $gapEnd = $gap[1] - $delta;

      // copy actual data before gap
      while ($i < $size && $this->times[$i] < $gapStart) {
        $time = $this->times[$i];
        $timeseries->data[] = $this->data[$i];
        $timeseries->times[] = $time;
        $i = $i + 1;
      }

      if ($gapEnd < $gapStart) {
        // doesn't seem like much of a gap
        continue;
      }

      // fill gap with empty value
      $g = 0;
      while ($time < $gapEnd) {
        $time = $gapStart + ($g * $delta);
        $timeseries->data[] = $emptyValue;
        $timeseries->times[] = $time;
        $g = $g + 1;
      }

    }

    // copy remaining actual data
    while ($i < $size) {
      $time = $this->times[$i];
      $timeseries->data[] = $this->data[$i];
      $timeseries->times[] = $time;
      $i = $i + 1;
    }

    return $timeseries;
  }

  /**
   * Assumes timeseries uses a regular sampling interval.
   *
   * @return {Array<gaps>}
   */
  public function getTimeGaps ($expectedDelta) {
    $gaps = array();
    $lastTime = null;

    $len = count($this->data);
    for ($i = 0; $i < $len; $i++) {
      $time = $this->times[$i];

      if ($lastTime != null) {
        if (($time - $lastTime) != $expectedDelta) {
          // a gap
          $gaps[] = array($lastTime, $time);
        }
      }

      $lastTime = $time;
    }

    return $gaps;
  }

  /**
   * Check if timeseries is empty.
   *
   * @return {Boolean}
   *     true if empty,
   *     false otherwise.
   */
  public function isEmpty () {
    return count($this->times) == 0;
  }

  /**
   * Generate an empty timeseries.
   *
   * A sample will only be generated for endtime
   * if endtime is an even multiple of delta from starttime.
   *
   * @param $startTime {Number}
   *     float epoch seconds timestamp.
   * @param $endTime {Number}
   *     float epoch seconds timestamp.
   * @param $delta {Number}
   *     float interval between samples.
   */
  public static function generateEmpty ($startTime, $endTime, $delta) {
    $data = array();
    $times = array();

    $count = intval($endTime - $startTime) / $delta + 1;
    for ($i = 0; $i < $count; $i++) {
      $data[] = null;
      $times[] = $startTime + ($i * $delta);
    }

    return new Timeseries($data, $times);
  }

}


?>
