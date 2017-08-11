<?php

include_once '../conf/config.inc.php';
include_once $LIB_DIR . '/classes/WaveServer.class.php';
include_once $LIB_DIR . '/classes/GeomagWebService.class.php';


if (!isset($TEMPLATE)) {
  // any parameters
  $validElements = array('E-E','E-N','D','E','H','F','G','SQ','SV','DIST','DST','UK1','UK2','UK3','UK4','X','Y','Z');
  sort($validElements);
  $metadata = array();
  $json = json_decode(file_get_contents('observatories.json'), true);
  foreach ($json['features'] as $obs) {
    $metadata[$obs['id']] = $obs;
  }

  if (count($_GET) != 0) {
    // if there are url parameters, process request
    $waveserver = new WaveServer($EDGE_HOST, $EDGE_PORT, $EDGE_TIMEOUT);
    $geomagService = new GeomagWebService($waveserver, $metadata);
    $geomagService->run();
    exit();
  }

  $TITLE = 'Geomag Web Service Usage';
  include 'template.inc.php';
}

?>


<h2>Example Requests</h3>

<dl>
  <dt>BOU observatory data for current UTC day in IAGA2002 format</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=BOU';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>

  <dt>BOU observatory data for current UTC day in JSON format</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=BOU&format=json';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>

  <dt>BOU electric field data for current UTC day in IAGA2002 format</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=BOU&elements=E-N,E-E';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>

</dl>


<h2>Request Limits</h2>

<p>
  To ensure availablility for users, the web service restricts the amount of
  data that can be retrieved in one request.  The amount of data requested
  is computed as follows, where interval is the number of seconds between
  starttime and endtime:
</p>

<pre>
  samples = count(elements) * interval / sampling_period
</pre>

<h3>Limits by output format</h3>
<dl>
  <dt>json</dt>
  <dd>
    <code>172800 samples</code> = 4 elements * 12 hours * 3600 samples/hour.
  </dd>

  <dt>iaga2002</dt>
  <dd>
    <code>345600 samples</code> = 4 elements * 24 hours * 3600 samples/hour.
  </dd>
</dl>

<p>
  NOTE: while the <code>json</code> format supports fewer total samples per
  request, users may request fewer elements to retrieve longer intervals.
</p>


<h2>Parameters</h2>
<dl>
  <dt>id</dt>
  <dd>
    Observatory code.
    Required.<br/>
    Valid values:
<?php
        echo '<code>' .
            implode('</code>, <code>', array_keys($metadata)) .
            '</code>';
?>
  </dd>

  <dt>starttime</dt>
  <dd>
    Time of first requested data.<br/>
    Default: start of current UTC day<br/>
    Format: ISO8601 (<code>YYYY-MM-DDTHH:MM:SSZ</code>)<br/>
    Example: <code><?php echo gmdate('Y-m-d\TH:i:s\Z'); ?></code>
  </dd>

  <dt>endtime</dt>
  <dd>
    Time of last requested data.<br/>
    Default: starttime + 24 hours<br/>
    Format: ISO8601 (<code>YYYY-MM-DDTHH:MM:SSZ</code>)<br/>
    Example: <code><?php echo gmdate('Y-m-d\TH:i:s\Z'); ?></code>
  </dd>

  <dt>elements</dt>
  <dd>
    Comma separated list of requested elements.<br/>
    Default: <code>X,Y,Z,F</code><br/>
    Valid values:
    <?php
            echo '<code>' .
                implode('</code>, <code>', array_values($validElements)) .
                '</code>';
    ?>
  </dd>

  <dt>sampling_period</dt>
  <dd>
    Interval in seconds between values.<br/>
    Default: <code>60</code><br/>
    Valid values:
      <code>1</code>,
      <code>60</code>,
      <code>3600</code>
  </dd>

  <dt>type</dt>
  <dd>
    Type of data.<br/>
    Default: <code>variation</code>
    Valid values:
      <code>variation</code>,
      <code>adjusted</code>,
      <code>quasi-definitive</code>,
      <code>definitive</code><br/>
    <small>
      NOTE: the USGS web service also supports specific EDGE location codes.
      For example:
          <code>R0</code> is "internet variation",
          <code>R1</code> is "satellite variation".
    </small>
  </dd>

  <dt>format</dt>
  <dd>
    Output format.<br/>
    Default: <code>iaga2002</code><br/>
    Valid values:
      <code>iaga2002</code>,
      <code>json</code>.
  </dd>
</dl>
