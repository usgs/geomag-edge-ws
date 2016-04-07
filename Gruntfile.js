'use strict';

module.exports = function (grunt) {

  var gruntConfig = require('./gruntconfig');

  gruntConfig.tasks.forEach(grunt.loadNpmTasks);
  grunt.initConfig(gruntConfig);

  grunt.registerTask('build', [
    'clean:build',
    'postcss:dev',
    'copy:build'
  ]);

  grunt.registerTask('dist', [
    'build',
    'clean:dist',
    'copy:dist',
    'postcss:dist',
    'connect:template',
    'configureRewriteRules',
    'configureProxies:dist',
    'connect:dist'
  ]);

  grunt.registerTask('default', [
    'build',
    'connect:template',
    'configureRewriteRules',
    'configureProxies:dev',
    'configureProxies:test',
    'connect:dev',
    'watch'
  ]);

};
