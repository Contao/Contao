'use strict';

var gulp = require('gulp'),
    csso = require('gulp-csso'),
    ignore = require('gulp-ignore'),
    rename = require('gulp-rename'),
    svgo = require('gulp-svgo'),
    pump = require('pump');

gulp.task('minify-theme-css', function (cb) {
    pump(
        [
            gulp.src('core-bundle/contao/themes/flexible/styles/*.css'),
            csso({
                comments: false,
                restructure: false
            }),
            rename({
                suffix: '.min'
            }),
            gulp.dest('core-bundle/contao/themes/flexible')
        ],
        cb
    );
});

gulp.task('minify-theme-icons', function (cb) {
    pump(
        [
            gulp.src('core-bundle/contao/themes/flexible/icons/*.svg'),
            svgo({
                multipass: true,
                plugins: [{
                    inlineStyles: {
                        onlyMatchedOnce: false
                    }
                }]
            }),
            gulp.dest('core-bundle/contao/themes/flexible/icons')
        ],
        cb
    );
});

gulp.task('minify-dark-theme-icons', function (cb) {
    pump(
        [
            gulp.src('core-bundle/contao/themes/flexible/icons-dark/*.svg'),
            svgo({
                multipass: true,
                plugins: [{
                    inlineStyles: {
                        onlyMatchedOnce: false
                    }
                }]
            }),
            gulp.dest('core-bundle/contao/themes/flexible/icons-dark')
        ],
        cb
    );
});

gulp.task('watch', function () {
    gulp.watch('core-bundle/contao/themes/flexible/styles/*.css', gulp.series('minify-theme-css'));
    gulp.watch('core-bundle/contao/themes/flexible/icons/*.svg', gulp.series('minify-theme-icons'));
    gulp.watch('core-bundle/contao/themes/flexible/icons-dark/*.svg', gulp.series('minify-dark-theme-icons'));
});

gulp.task('default', gulp.parallel('minify-theme-css', 'minify-theme-icons', 'minify-dark-theme-icons'));
