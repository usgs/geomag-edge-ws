<?php

class JsonOutputFormat {

  const ISO8601 = 'Y-m-d\TH:i:s\Z';

  public function output($data, $query, $metadata) {
    global $HOST_URL_PREFIX;

    $data_type = $query->type;
    $elements = $query->elements;
    $endtime = $query->endtime;
    $sampling_period = $query->sampling_period;
    $starttime = $query->starttime;
    $station = $query->id;

    $station_metadata = $metadata[$station];
    $props = $station_metadata['properties'];
    $coords = $station_metadata['geometry']['coordinates'];
    $agency_name = $props['agency_name'];
    $station_name = $props['station_name'];
    $latitude = $coords[1];
    $longitude = $coords[0];
    $elevation = $coords[2];
    $sensor_orientation = $props['sensor_orientation'];
    $sensor_sampling_rate = $props['sensor_sampling_rate'];
    $declination_base = $props['declination_base'];
    $data_interval_type = ($sampling_period === 60 ?
        'filtered 1-minute (00:15-01:45)' :
        'average 1-second');

    // time array
    $times = $data['times'];
    // convert to iso8601
    $times = array_map(function ($t) {
      return gmdate(self::ISO8601, $t);
    }, $times);

    // data arrays
    $values = array();
    foreach ($elements as $element) {
      $el = $data[$element];
      $response = $el['response'];
      $sncl = $el['sncl'];
      $element_values = $el['values'];

      $values[] = array(
        'id' => $element,
        'metadata' => array(
          'element' => $element,
          'network' => $sncl['network'],
          'station' => $sncl['station'],
          'channel' => $sncl['channel'],
          'location' => $sncl['location'],
          'flag' => $response->flag
        ),
        'values' => $element_values
      );
    }

    $response = array(
      'type' => 'Timeseries',
      'metadata' => array(
        'intermagnet' => array(
          'imo' => array(
            'iaga_code' => $station,
            'name' => $station_name,
            'coordinates' => $coords
          ),
          'reported_orientation' => implode('', $elements),
          'sensor_orientation' => $sensor_orientation,
          'data_type' => $query->type,
          'sampling_period' => $query->sampling_period,
          'digital_sampling_rate' => $sensor_sampling_rate
        ),
        'status' => 200,
        'generated' => gmdate(self::ISO8601),
        'url' => $HOST_URL_PREFIX . $_SERVER['REQUEST_URI'],
        'api' => GeomagWebService::VERSION
      ),
      'times' => $times,
      'values' => $values
    );

    header('Content-Type: application/json; charset=utf-8');
    echo str_replace('\/', '/', JsonOutputFormat::safe_json_encode($response));
  }

  /**
   * UTF8 encode a data structure.
   *
   * from http://stackoverflow.com/questions/10199017/how-to-solve-json-error-utf8-error-in-php-json-decode
   *
   * @param $mixed {Mixed}
   *        value to utf8 encode.
   * @return {Mixed}
   *         utf8 encoded value.
   */
  public static function utf8_encode_array($mixed) {
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = self::utf8_encode_array($value);
        }
    } else if (is_string ($mixed)) {
        return utf8_encode($mixed);
    }
    return $mixed;
  }

  /**
   * Safely json_encode values.
   *
   * Handles malformed UTF8 characters better than normal json_encode.
   * from http://stackoverflow.com/questions/10199017/how-to-solve-json-error-utf8-error-in-php-json-decode
   *
   * @param $value {Mixed}
   *        value to encode as json.
   * @return {String}
   *         json encoded value.
   * @throws Exception when unable to json encode.
   */
  public static function safe_json_encode($value){
    $encoded = json_encode($value);
    $lastError = json_last_error();
    switch ($lastError) {
      case JSON_ERROR_NONE:
        return $encoded;
      case JSON_ERROR_UTF8:
        return self::safe_json_encode(self::utf8_encode_array($value));
      default:
        throw new Exception('json_encode error (' . $lastError . ')');
    }
  }

}
