'use strict';

var config = require('./config');

var watch = {
  scss: {
    files: [
      config.src + '/htdocs/**/*.css',
      config.src + '/htdocs/**/*.scss'
    ],
    tasks: [
      'postcss:dev'
    ],
    options: {
      livereload: true
    }
  },

  livereload: {
    files: [
      config.src + '/**/*',
      '!' + config.src + '/**/*.css',
      '!' + config.src + '/**/*.scss'
    ],
    tasks: ['copy:build'],
    options: {
      livereload: true
    }
  },

  gruntfile: {
    files: [
      'Gruntfile.js',
      'gruntconfig/**/*.js'
    ],
    tasks: ['jshint:gruntfile']
  }
};

module.exports = watch;
