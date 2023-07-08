/**
 * @file
 * Grunt tasks.
 *
 * Run `grunt` for to process with dev settings.
 * Run `grunt prod` to process with prod settings.
 * Run `grunt watch` to start watching with dev settings.
 */

/* global module */
var themePath = 'docroot/sites/all/themes/custom/wellnesspolygon/';
var libraryPath = 'docroot/sites/all/libraries/';
var themeFileNames = 'wp';

const sass = require('node-sass');

module.exports = function (grunt) {
  'use strict';
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    clean: {
      js: [
        themePath + 'js/wp.min.js'
      ]
    },
    sass_globbing: {
      dev: {
        files: {
          [themePath + 'scss/_variables.scss']: themePath + 'scss/variables/**/*.scss',
          [themePath + 'scss/_mixins.scss']: themePath + 'scss/mixins/**/*.scss',
          [themePath + 'scss/_base.scss']: themePath + 'scss/base/**/*.scss',
          [themePath + 'scss/_layout.scss']: themePath + 'scss/layout/**/*.scss',
          [themePath + 'scss/_components.scss']: themePath + 'scss/components/**/*.scss',
          [themePath + 'scss/_pages.scss']: themePath + 'scss/pages/**/*.scss'
        },
        options: {
          useSingleQuotes: true,
          signature: '//\n// GENERATED FILE. DO NOT MODIFY DIRECTLY.\n//'
        }
      }
    },
    concat: {
      options: {
        separator: '\n\n'
      },
      dist: {
        src: [
          libraryPath + 'bootstrap/assets/javascripts/bootstrap.js',
          themePath + 'js/*.js',
          themePath + 'js/**/*.js',
          '!' + themePath + 'js/' + themeFileNames + '.min.js'
        ],
        dest: themePath + 'js/' + themeFileNames + '.min.js'
      }
    },
    uglify: {
      prod: {
        options: {
          mangle: {
            except: ['jQuery', 'Drupal']
          },
          compress: {
            drop_console: true
          }
        },
        files: {
          [themePath + 'js/' + themeFileNames + '.min.js']: [themePath + 'js/' + themeFileNames + '.min.js']
        }
      },
      dev: {
        options: {
          mangle: {
            except: ['jQuery', 'Drupal']
          },
          compress: {
            drop_console: false
          }
        },
        files: {
          [themePath + 'js/' + themeFileNames + '.min.js']: [themePath + 'js/' + themeFileNames + '.min.js']
        }
      }
    },
    sass: {
      dev: {
        files: {
          [themePath + 'css/' + themeFileNames + '.min.css']: themePath + 'scss/style.scss'
        },
        options: {
          implementation: sass,
          sourceMap: true,
          outputStyle: 'expanded'
        }
      },
      prod: {
        files: {
          [themePath + 'css/' + themeFileNames + '.min.css']: themePath + 'scss/style.scss'
        },
        options: {
          implementation: sass,
          sourceMap: false,
          outputStyle: 'compressed'
        }
      }
    },
    watch: {
      options: {
        livereload: true
      },
      scripts: {
        files: [themePath + 'js/**/*.js'],
        tasks: ['clean:js', 'concat', 'uglify:dev'],
        options: {
          spawn: false
        }
      },
      styles: {
        files: [themePath + 'scss/**/*.scss'],
        tasks: ['sass_globbing', 'sass:dev'],
        options: {
          spawn: false
        }
      }
    }
  });

  grunt.loadNpmTasks('grunt-sass-globbing');
  grunt.loadNpmTasks('grunt-contrib-concat');
  grunt.loadNpmTasks('grunt-contrib-clean');
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-sass');
  grunt.loadNpmTasks('grunt-exec');

  grunt.registerTask('prod', ['clean:js', 'sass_globbing', 'concat', 'sass:prod']);
  // By default, run grunt with dev settings.
  grunt.registerTask('default', ['clean:js', 'sass_globbing', 'concat', 'uglify:dev', 'sass:dev']);
};
