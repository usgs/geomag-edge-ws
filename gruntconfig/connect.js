'use strict';

var config = require('./config'),
    extend = require('extend');


var jsonUtf8Middleware;

jsonUtf8Middleware = function(req, res, next) {
  if(/\s*.json/.test(req.url)) {
    res.setHeader('Content-Type', 'application/json; charset=utf-8');
  }
  return next();
};


var connect = {
  options: {
    hostname: '*'
  },

  rules: [
    {
      from: '^(' + config.ini.MOUNT_PATH + ')?/(index)\\.(json)\\??(.*)$',
      to: '/$2.php?format=$3&$4'
    },
    {
      from: '^' + config.ini.MOUNT_PATH + '/(.*)$',
      to: '/$1'
    }
  ],

  proxies: [
    {
      context: '/theme/',
      host: 'localhost',
      port: config.templatePort,
      rewrite: {
        '^/theme': ''
      }
    }
  ],

  dev: {
    options: {
      base: [config.build + '/' + config.src + '/htdocs'],
      port: config.srcPort,
      livereload: true,
      open: 'http://127.0.0.1:' + config.srcPort + config.ini.MOUNT_PATH + '/',
      middleware: function (connect, options, middlewares) {
        middlewares.unshift(
          jsonUtf8Middleware,
          require('grunt-connect-rewrite/lib/utils').rewriteRequest,
          require('grunt-connect-proxy/lib/utils').proxyRequest,
          require('gateway')(options.base[0], {
            '.php': 'php-cgi',
            'env': extend({}, process.env, {
              'PHPRC': 'node_modules/hazdev-template/dist/conf/php.ini'
            })
          })
        );
        return middlewares;
      }
    }
  },

  dist: {
    options: {
      base: [config.dist + '/htdocs'],
      port: config.distPort,
      keepalive: true,
      open: 'http://127.0.0.1:' + config.distPort + config.ini.MOUNT_PATH + '/',
      middleware: function (connect, options, middlewares) {
        middlewares.unshift(
          jsonUtf8Middleware,
          require('grunt-connect-rewrite/lib/utils').rewriteRequest,
          require('grunt-connect-proxy/lib/utils').proxyRequest,
          require('gateway')(options.base[0], {
            '.php': 'php-cgi',
            'env': extend({}, process.env, {
              'PHPRC': 'node_modules/hazdev-template/dist/conf/php.ini'
            })
          })
        );
        return middlewares;
      }
    }
  },

  template: {
    options: {
      base: ['node_modules/hazdev-template/dist/htdocs'],
      port: config.templatePort
    }
  }
};


module.exports = connect;
