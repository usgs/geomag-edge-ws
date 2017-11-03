<?php

include_once $LIB_DIR . '/classes/GeomagQuery.class.php';
include_once $LIB_DIR . '/classes/Iaga2002OutputFormat.class.php';
include_once $LIB_DIR . '/classes/JsonOutputFormat.class.php';
include_once $LIB_DIR . '/classes/Timeseries.class.php';
include_once $LIB_DIR . '/classes/WebService.class.php';


class GeomagWebService extends WebService {

  const VERSION = '{{VERSION}}';

  public $waveserver;
  public $metadata;

  /**
   * Construct a new GeomagWebService.
   *
   * @param $waveserver {WaveServer}
   *    Waveserver object used to fetch data.
   * @param $metadata {Array<String, Array>}
   *        Associative array of metadata, keyed by upper case observatory id.
   */
  public function __construct($waveserver, $metadata) {
    parent::__construct(self::VERSION);
    $this->waveserver = $waveserver;
    $this->metadata = $metadata;
  }

  /**
   * Run web service.
   */
  public function run() {
    try {
      $query = $this->parseQuery($_GET);
    } catch (Exception $e) {
      $this->error(self::BAD_REQUEST, $e->getMessage());
    }

    // validate requested interval.
    $interval = $query->endtime - $query->starttime;
    $format = $query->format;
    $sampling_period = $query->sampling_period;

    $requested_samples = count($query->elements) * $interval / $sampling_period;
    if ($format === 'iaga2002') {
      // streaming supports more samples
      // 345600 = 4 elements * 24 hours * 3600 samples/hour
      // 44640 = 31 days * 24 hours/day * 60 samples/hour
      if ($requested_samples > 345600) {
        $this->error(self::BAD_REQUEST,
            'IAGA2002 format is limited to 345600 samples per request');
      }
    } else { // json
      // 172800 = 4 elements * 12 hours * 3600 seconds/hour * 1 sample/second
      if ($requested_samples > 172800) {
        $this->error(self::BAD_REQUEST,
            'JSON format is limited to 172800 samples per request');
      }
    }

    try {
      // open socket to waveserver,
      // so errors connecting can be caught before any output
      $this->waveserver->connect();

      // only cache for 5 minutes
      global $APP_DIR;
      $CACHE_MAXAGE = 300;
      include $APP_DIR . '/lib/cache.inc.php';

      if ($query->format === 'iaga2002') {
        $output = new Iaga2002OutputFormat();
        $output->run($this, $query, $this->metadata);
      } else {
        // query format is json
        $data = $this->getData($query);
        $output = new JsonOutputFormat();
        $output->output($data, $query, $this->metadata);
      }
    } catch (Exception $e) {
      $this->error(self::SERVER_ERROR, $e->getMessage());
    }
  }

  /**
   * Get requested data.
   *
   * @param $query {GeomagQuery}
   *        web service query.
   * @return {Array<String, WaveServerResponse}
   *         associative array of data.
   *         keys are requested elements.
   */
  public function getData($query) {
    $data = [];
    $times = null;

    $endtime = $query->endtime;
    $sampling_period = $query->sampling_period;
    $starttime = $query->starttime;
    $station = $query->id;
    $type = $query->type;

    foreach ($query->elements as $element) {
      $sncl = $this->getSNCL(
          $station,
          $element,
          $query->sampling_period,
          $query->type);

      $response = $this->waveserver->get(
          $starttime,
          // when requesting only one sample, need to request following sample
          // this is trimmed during extend below
          $endtime + ($starttime === $endtime ? $query->sampling_period : 0),
          $sncl['station'],
          $sncl['network'],
          $sncl['channel'],
          $sncl['location']);

      $timeseries = $response->toTimeseries();

      $data[$element] = array(
        'sncl' => $sncl,
        'element' => $element,
        'response' => $response,
        'timeseries' => $timeseries,
        'values' => null
      );

      if ($timeseries->isEmpty()) {
        // no data
        continue;
      }

      // fill/trim to starttime/endtime
      $timeseries = $timeseries->extend($starttime, $endtime);

      $timeseriesTimes = $timeseries->times;
      if ($times == null) {
        // use first times that exist
        $times = $timeseriesTimes;
        $size = count($times);
        $data['times'] = $times;
      } else {
        // make sure times match
        if ($size != count($timeseriesTimes)) {
          throw new Exception('inconsistent channel length');
        }
        for ($i = 0; $i < $size; $i++) {
          if ($times[$i] != $timeseriesTimes[$i]) {
            throw new Exception('inconsistent channel times');
          }
        }
      }

      // scale values
      $data[$element]['values'] = array_map(function ($v) {
        return ($v == null ? null : $v / 1000);
      }, $timeseries->data);
    }

    // if all channels empty, generate times
    if ($times == null) {
      $times = Timeseries::generateEmpty(
          $starttime,
          $endtime,
          $sampling_period)->times;
      $size = count($times);
      $data['times'] = $times;
    }

    // generate empty channels
    foreach ($query->elements as $element) {
      if ($data[$element]['values'] == null) {
        $timeseries = Timeseries::generateEmpty(
            $times[0],
            $times[$size - 1],
            $times[1] - $times[0]);
        $data[$element]['timeseries'] = $timeseries;
        $data[$element]['values'] = $timeseries->data;
      }
    }

    return $data;
  }

  /**
   * Translate requested elements to EDGE SNCL codes.
   *
   * @param $station {String}
   *        observatory.
   * @param $element {Array<String>}
   *        requested elements.
   * @param $sampling_period {Number}
   *        1 for seconds, 60 for minutes.
   * @param $type {String}
   *        'variation', 'adjusted', 'quasi-definitive', or 'definitive'.
   */
  protected function getSNCL($station, $element, $sampling_period, $type) {
    $network = 'NT';
    $station = $station;

    $channel = '';
    if ($sampling_period === 60) {
      $prefix = 'M';
    } else if ($sampling_period === 1) {
      $prefix = 'S';
    }

    $element = strtoupper($element);
    switch ($element) {
      case 'E-E':
        $channel = $prefix . 'QE';
        break;
      case 'E-N':
        $channel = $prefix . 'QN';
        break;
      case 'D':
      case 'E':
      case 'H':
      case 'X':
      case 'Y':
      case 'Z':
        $channel = $prefix . 'V' . $element;
        break;
      case 'F':
      case 'G':
        $channel = $prefix . 'S' . $element;
        break;
      case 'SQ':
      case 'SV':
        $channel = $prefix . $element;
        break;
      case 'DIST':
        $channel = $prefix . 'DT';
        break;
      case 'DST':
        $channel = $prefix . 'GD';
        break;
      default:
        if (preg_match('/^[A-Z][A-Z0-9]{2}$/', $element)) {
          // seems like an edge channel code
          $channel = $element;
        } else {
          $this->error(self::BAD_REQUEST, 'Unknown element "' . $element . '"');
        }
        break;
    }

    if ($type === 'variation') {
      $location = 'R0';
    } else if ($type === 'adjusted') {
      $location = 'A0';
    } else if ($type === 'quasi-definitive') {
      $location = 'Q0';
    } else if ($type === 'definitive') {
      $location = 'D0';
    } else {
      $location = $type;
    }

    return array(
      'station' => $station,
      'network' => $network,
      'channel' => $channel,
      'location' => $location
    );
  }

  protected function parseQuery($params) {
    $query = new GeomagQuery();

    foreach ($params as $name => $value) {
      if ($value === '') {
        // treat empty values as missing parameters
        continue;
      }
      if ($name === 'id') {
        $query->id = $this->validateEnumerated($name, strtoupper($value),
            array_keys($this->metadata));
      } else if ($name === 'starttime') {
        $query->starttime = $this->validateTime($name, $value);
      } else if ($name === 'endtime') {
        $query->endtime = $this->validateTime($name, $value);
      } else if ($name === 'elements') {
        if (!is_array($value)) {
          $value = explode(',', $value);
        }
        $value = array_map(function ($value) {
          return strtoupper($value);
        }, $value);
        $query->elements = $value;
      } else if ($name === 'sampling_period') {
        $query->sampling_period = intval(
            $this->validateEnumerated($name, $value,
                // valid sampling periods
                // 1 = second
                // 60 = minute
                // 3600 = hour
                array(1, 60, 3600)));
      } else if ($name === 'type') {
        if (preg_match('/^[A-Z0-9]{2}$/', $value)) {
          // edge location code
          $query->type = $value;
        } else {
          $query->type = $this->validateEnumerated($name, $value,
              array('variation', 'adjusted', 'quasi-definitive', 'definitive'));
        }
      } else if ($name === 'format') {
        $query->format = $this->validateEnumerated($name, strtolower($value),
              array('iaga2002', 'json'));
      } else {
        $this->error(self::BAD_REQUEST, 'Unknown parameter "' . $name . '"');
      }
    }

    // set defaults
    if ($query->id === null) {
      throw new Exception('"id" is a required parameter');
    }
    if ($query->starttime === null) {
      $query->starttime = strtotime(gmdate('Y-m-d'));
    }
    if ($query->endtime === null) {
      // default to starttime + 24 hours
      $query->endtime = $query->starttime + (24 * 60 * 60 - 1);
    }
    if ($query->elements === null) {
      // default when not specified
      $query->elements = array('X', 'Y', 'Z', 'F');
    }
    //
    if (count($query->elements) > 4 && $query->format === 'iaga2002'){
      throw new Exception('IAGA2002 format is limited to 4 elements per request');
    }

    return $query;
  }

}
