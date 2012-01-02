#!/usr/bin/env python

### BEGIN INIT INFO
# Provides:             cgi-apps
# Required-Start:   
# Required-Stop:    
# Default-Start:        2 3 4 5
# Default-Stop:         0 1 6
# Short-Description:    fcgi initscript
# Description:          starts fcgi daemons for web-applications
### END INIT INFO

from subprocess import call, PIPE
from os import setuid, path, environ, chdir
from pwd import getpwnam
from sys import argv, exit, stdout
from copy import copy
from ConfigParser import RawConfigParser as ConfigParser

PID_DIR = '/tmp'
USER = 'www-data'
CONFIG_PATH = '/etc/default/fcgi'

configuration = None

def status(text):
    stdout.write(text)
    stdout.flush()

def read_fcgi_config():
    global configuration
    conf = ConfigParser()
    conf.read([CONFIG_PATH])
    configuration = conf

def configure_environment(environment_path):
    """This function must set the proper $VIRTUALENV and $PATH
    for the target application"""
    app_environment = copy(environ)
    app_environment['PATH'] = "%s:%s" % (path.join(environment_path, 'bin'), app_environment['PATH'])
    app_environment['VIRTUAL_ENV'] = environment_path
    return app_environment

def find_apprunner(app_config, environ):
    for apprunner in ['paster', 'pserve']:
        find_call = ['which', apprunner]
        retval = call(find_call, env=environ, stdout=PIPE, stderr=PIPE)
        if retval == 0:
            return apprunner
    return False

def start_apps(app_list):
    for app in app_list:
        app_config = dict(configuration.items(app))
        app_environment = configure_environment(app_config['environ'])
        pid_path = path.join(PID_DIR, app + '.pid')
        apprunner = find_apprunner(app_config, app_environment)
        if apprunner == 'paster':
            runner_command = ['paster', 'serve', '--daemon', '--pid-file=%s' % pid_path, app_config['configuration']]
            chdir(app_config['root'])
            status('Starting app (with paster) %s...' % app)
        elif apprunner == 'pserve':
            runner_command = ['pserve', app_config['configuration'], '--daemon']
            chdir(app_config['root'])
            status('Starting app (with pserve) %s...' % app)
        else:
            status('could not determine apprunner\n')
            return False

        retval = call(runner_command, env=app_environment, stdout=PIPE, stderr=PIPE)
        if retval == 0:
            status('done\n')
        else:
            status('FAILED\n')

def stop_apps(app_list):
    for app in app_list:
        app_config = dict(configuration.items(app))
        app_environment = configure_environment(app_config['environ'])
        pid_path = path.join(PID_DIR, app + '.pid')
        chdir(app_config['root'])
        apprunner = find_apprunner(app_config, app_environment)
        if apprunner == 'paster':
            runner_command = ['paster', 'serve', '--stop-daemon', '--pid-file=%s' % pid_path, app_config['configuration']]
            chdir(app_config['root'])
            status('Terminating app (with paster) %s...' % app)
        elif apprunner == 'pserve':
            runner_command = ['pserve', app_config['configuration'], '--stop-daemon']
            chdir(app_config['root'])
            status('Terminating app (with pserve) %s...' % app)
        else:
            status('could not determine apprunner\n')
            return False

        retval = call(runner_command, env=app_environment, stdout=PIPE, stderr=PIPE)
        if retval == 0:
            status('done\n')
        else:
            status('FAILED\n')

if __name__ == '__main__':
    read_fcgi_config()

    setuid(getpwnam(USER).pw_uid)

    app = None
    if len(argv) > 2:
        apps = [argv[2]]
    else:
        apps = configuration.sections()
    try:
        if argv[1] == 'start':
            start_apps(apps)
        elif argv[1] == 'stop':
            stop_apps(apps)
        elif argv[1] == 'restart':
            stop_apps(apps)
            start_apps(apps)
        else:
            raise IndexError()
    except IndexError:
        print 'Usage: %s {start|stop|restart} [<appname>]' % argv[0]
        exit(1)

    exit()
