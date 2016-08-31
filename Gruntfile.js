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

  grunt.registerTask('builddist', [
    'build',
    'clean:dist',
    'copy:dist',
    'postcss:dist'
  ]);

  grunt.registerTask('rundist', [
    'connect:template',
    'configureRewriteRules',
    'configureProxies:dist',
    'connect:dist'
  ]);

  grunt.registerTask('dist', [
    'builddist',
    'rundist'
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
