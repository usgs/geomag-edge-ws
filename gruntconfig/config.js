'use strict';

var fs = require('fs'),
    ini = require('ini');

var configIni = ini.parse(fs.readFileSync('./src/conf/config.ini').toString());


var config = {
  ini: configIni,

  build: '.build',
  dist: 'dist',
  distPort: 8202,
  etc: 'etc',
  example: 'example',
  examplePort: 8204,
  lib: 'lib',
  src: 'src',
  srcPort: 8200,
  templatePort: 8203,
  test: 'test',
  testPort: 8201
};

module.exports = config;
