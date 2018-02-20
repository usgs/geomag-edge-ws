<?php

class Iaga2002OutputFormat {

  static $EMPTY_CHANNEL = 'NUL';
  static $EMPTY_CHANNEL_VALUE = '88888.00';
  static $EMPTY_VALUE = '99999.00';


  /**
   * Run web service query, and generate IAGA2002 output.
   *
   * @param $service {GeomagWebService}
   *     used to make incremental data requests via $service->getData().
   * @param $query {GeomagQuery}
   *     the query to run.
   * @param $metadata {Array}
   *     associative array of metadata.
   */
  public function run ($service, $query, $metadata) {
    $starttime = $query->starttime;
    $endtime = $query->endtime;
    $sampling_period = $query->sampling_period;


    header('Content-Type: text/plain');

    echo $this->formatHeaders($query, $metadata);
    echo $this->formatRequestInfo($query);
    echo $this->formatDataHeader($query->id, $query->elements);

    // default to one day at a time
    $delta = 24 * 3600;
    if ($sampling_period === 1) {
      // six hours at a time for seconds
      $delta = 6 * 3600;
    }
    $time = $starttime;
    while ($time <= $endtime) {
      $nexttime = min($time + $delta - $sampling_period, $endtime);

      $query->starttime = $time;
      $query->endtime = $nexttime;

      $data = $service->getData($query);
      echo $this->formatData($data, $query);
      $data = null;

      $time = $nexttime + $sampling_period;
    }
  }

  public function output($data, $query, $metadata) {
    header('Content-Type: text/plain');

    print $this->formatHeaders($query, $metadata);
    print $this->formatRequestInfo($query);
    print $this->formatDataHeader($query->id, $query->elements);
    print $this->formatData($data, $query);
  }

  protected function formatHeaders($query, $metadata) {
    $station = $query->id;
    $elements = $query->elements;
    $station_metadata = $metadata[$station];
    $props = $station_metadata['properties'];
    $coords = $station_metadata['geometry']['coordinates'];
    $agency_name = $props['agency_name'];
    $station_name = $props['name'];
    $latitude = $coords[1];
    $longitude = $coords[0];
    $elevation = $coords[2];
    $sensor_orientation = $props['sensor_orientation'];
    $sensor_sampling_rate = $props['sensor_sampling_rate'];
    $declination_base = $props['declination_base'];
    $data_interval_type = ($query->sampling_period === 60 ?
        'filtered 1-minute (00:15-01:45)' :
        'average 1-second');
    $data_type = $query->type;

    $buf = '';
    $buf .= $this->formatHeader('Format', 'IAGA-2002');
    $buf .= $this->formatHeader('Source of Data', $agency_name);
    $buf .= $this->formatHeader('Station Name', $station_name);
    $buf .= $this->formatHeader('IAGA CODE', $station);
    $buf .= $this->formatHeader('Geodetic Latitude', $latitude);
    $buf .= $this->formatHeader('Geodetic Longitude', $longitude);
    $buf .= $this->formatHeader('Elevation', $elevation);
    $buf .= $this->formatHeader('Reported', implode('', $elements));
    $buf .= $this->formatHeader('Sensor Orientation', $sensor_orientation);
    $buf .= $this->formatHeader('Digital Sampling',
        $sensor_sampling_rate . ' second');
    $buf .= $this->formatHeader('Data Interval Type', $data_interval_type);
    $buf .= $this->formatHeader('Data Type', $data_type);
    if ($data_type === 'variation') {
      $buf .= $this->formatComment('DECBAS               ' . $declination_base);
    }
    return $buf;
  }

  protected function formatHeader($name, $value) {
    static $prefix = ' ';
    static $suffix = " |\n";
    return $prefix .
        str_pad($name, 23) .
        str_pad($value, 44) .
        $suffix;
  }

  protected function formatComment($comment) {
    static $prefix = ' # ';
    static $suffix = " |\n";
    return $prefix .
        implode($suffix . $prefix, array_map(function ($l) {
          return str_pad($l, 65);
        }, explode("\n", wordwrap($comment, 65, "\n", true)))) .
        $suffix;
  }

  protected function formatDataHeader($station, $channels) {
    static $prefix = 'DATE       TIME         DOY  ';
    static $suffix = "|\n";

    while (count($channels) < 4) {
      $channels[] = self::$EMPTY_CHANNEL;
    }
    $r = '';
    foreach ($channels as $channel) {
      $r .= '   ' . str_pad($station . $channel, 7);
    }
    return $prefix . $r . $suffix;
  }

  protected function formatData($data, $query) {
    $elements = $query->elements;

    // time array
    $times = $data['times'];

    // build data arrays
    $element_data = array();
    foreach ($elements as $el) {
      $element_data[] = $data[$el]['values'];
    }

    // loop over arrays, outputting one row at a time
    $buf = '';
    for ($i = 0, $len = count($times); $i < $len; $i++) {
      $values = array();
      foreach ($element_data as $d) {
        $value = $d[$i];
        if ($value === null) {
          $value = self::$EMPTY_VALUE;
        } else {
          $value = number_format($value, 2, '.', '');
        }
        $values[] = $value;
      }
      while (count($values) < 4) {
        $values[] = self::$EMPTY_CHANNEL_VALUE;
      }
      $buf .= $this->formatValues($times[$i], $values);
    }
    return $buf;
  }

  /**
   * Format request information for iaga comments section.
   *
   * @param $query {GeomagQuery}
   *     the request query.
   * @return {String}
   *     formatted content.
   */
  protected function formatRequestInfo($query) {
    global $EDGE_WS_VERSION;
    global $HOST_URL_PREFIX;
    global $MOUNT_PATH;

    $buf = '';

    $buf .= $this->formatComment('');
    $buf .= $this->formatComment('Request: ' . $_SERVER['REQUEST_URI']);
    $buf .= $this->formatComment('Request submitted: ' .
        gmdate('Y-m-d\TH:i:s\Z'));
    $buf .= $this->formatComment('Service URL: ' .
        $HOST_URL_PREFIX . $MOUNT_PATH . '/');
    $buf .= $this->formatComment('Service version: ' . $EDGE_WS_VERSION);

    return $buf;
  }

  /**
   * Format an IAGA value row.
   *
   * @param $time {Number}
   *        epoch timestamp corresponding to row.
   * @param $values {Array<Number>}
   *        array with 4 elements to be output.
   */
  protected function formatValues($time, $values) {
    $doy = gmdate('z', $time) + 1;
    return WebService::formatISO8601($time, ' ', '') .
        ' ' . str_pad($doy, 3, '0', STR_PAD_LEFT) .
        '   ' .
        ' ' . str_pad($values[0], 9, ' ', STR_PAD_LEFT) .
        ' ' . str_pad($values[1], 9, ' ', STR_PAD_LEFT) .
        ' ' . str_pad($values[2], 9, ' ', STR_PAD_LEFT) .
        ' ' . str_pad($values[3], 9, ' ', STR_PAD_LEFT) .
        "\n";
  }

}
