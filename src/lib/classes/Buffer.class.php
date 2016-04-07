<?php


/**
 * A class for reading binary data stored in a string.
 */
class Buffer {

  public $data;
  public $endian;
  public $len;
  public $pos;
  public $systemEndian;

  /**
   * Construct a new buffer.
   *
   * @param $data {String}
   *        string of bytes.
   * @param $endian {>|<}
   *        default '>'.
   *        how to interpret numbers read from string.
   *        '>' is big endian.
   *        '<' is little endian.
   * @param $pos {Integer}
   *        default 0.
   *        current position within string.
   */
  public function __construct($data, $endian='>', $pos=0) {
    if ($endian !== '>' && $endian !== '<') {
      throw new Exception('Invalid endian code "' . $endian . '",' .
          ' must be "<" or ">"');
    }
    $this->data = $data;
    $this->endian = $endian;
    $this->len = strlen($data);
    $this->pos = $pos;
    // check system endianness
    $test = unpack('C*', pack('i', 1));
    $this->systemEndian = ($test[1] === 1) ? '<' : '>';
  }

  /**
   * Check how many bytes are available.
   *
   * @return {Integer} number of available bytes.
   */
  public function available() {
    return $this->len - $this->pos;
  }

  /**
   * Read a specific type from buffer.
   *
   * @param $type {String}
   *        's' - string
   *        'i' - integer
   *        'd' - double
   * @param $count {Integer}
   *        When $type is 's', read $count characters in order.
   *        Otherwise, read $count integers or doubles using buffer byte order.
   * @return {Array<?>}
   *         array of parsed data.
   */
  public function get($type, $count = 1) {
    $read = array();
    // parse requested format
    if ($type === 's') {
      $read[] = $this->read($count);
    } else {
      for ($i = 0; $i < $count; $i++) {
        if ($type === 'd') {
          $size = 8;
        } else if ($type === 'i') {
          $size = 4;
        }
        $chunk = $this->read($size);
        if ($this->systemEndian !== $this->endian) {
          $chunk = self::swap($chunk);
        }
        $read[] = unpack($type, $chunk)[1];
      }
    }
    return $read;
  }

  /**
   * Read bytes from buffer.
   *
   * @param $count {Integer}
   *        default 1.
   *        number of bytes.
   */
  public function read($count = 1) {
    $available = $this->available();
    if ($count > $available) {
      throw new Exception('Attempting to read ' . $count . ' bytes,' .
          ' only ' . $available);
    }
    $data = substr($this->data, $this->pos, $count);
    $this->pos += $count;
    return $data;
  }

  /**
   * Unpack using a python-style format string.
   *
   * @param $format {String}
   *        python-style format string.
   * @return {Array}
   *         parsed data.
   */
  public function unpack($format) {
    $read = array();

    $format_len = strlen($format);
    $format_pos = 0;
    while ($format_pos < $format_len) {
      // read count
      $count = 1;
      $count_pos = $format_pos;
      while (is_numeric($format[$count_pos])) {
        $count_pos++;
      }
      if ($count_pos !== $format_pos) {
        $count = intval(substr($format, $format_pos, $count_pos-$format_pos));
        $format_pos = $count_pos;
      }
      // read type
      $type = $format[$format_pos++];
      // parse for type and count
      $read = array_merge($read, $this->get($type, $count));
    }

    return $read;
  }

  /**
   * Swap the "byte" order in a string.
   *
   * @param $chunk {String}
   *        string to reverse.
   */
  public static function swap($chunk) {
    // unpack bytes into array
    $bytes = unpack('C*', $chunk);
    // reverse order
    $bytes = array_reverse($bytes);
    // use pack to join array
    return call_user_func_array('pack', array_merge(array('C*'), $bytes));
  }

}
