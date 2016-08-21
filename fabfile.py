import subprocess
import sys
import os.path
from fabric.api import run, env, sudo
from fabric.contrib import files
import time

# Internal settings
STAGING = True
REPO_URL = 'https://github.com/DamianZaremba/cluebotng.git'
CORE_RELEASE = '242d7a6'
env.hosts = ['tools-login.wmflabs.org']
env.use_ssh_config = True
env.sudo_prefix = "/usr/bin/sudo -ni"


def check_workingdir_clean():
    '''
    Internal function, checks for any uncommitted local changes
    '''
    p = subprocess.Popen(['git', 'diff', '--exit-code'],
                         stdout=subprocess.PIPE,
                         stderr=subprocess.PIPE)
    p.communicate()

    if p.returncode != 0:
        print('There are local, uncommited changes.')
        print('Refusing to deploy.')
        sys.exit(1)


def check_remote_up2date():
    '''
    Internal function, ensures the local HEAD hash is the same as the remote HEAD hash for master
    '''
    p = subprocess.Popen(['git',
                          'ls-remote',
                          REPO_URL,
                          'master'],
                         stdout=subprocess.PIPE,
                         stderr=subprocess.PIPE)
    remote_sha1 = p.communicate()[0].split('\t')[0].strip()

    p = subprocess.Popen(['git', 'rev-parse', 'HEAD'],
                         stdout=subprocess.PIPE,
                         stderr=subprocess.PIPE)
    local_sha1 = p.communicate()[0].strip()

    if local_sha1 != remote_sha1:
        print('There are comitted changes, not pushed to github.')
        print('Refusing to deploy.')
        sys.exit(1)


def _setup():
    '''
    Internal function, configures the correct environment directories
    '''
    if not files.exists(os.path.join(TOOL_DIR, 'apps')):
        sudo('mkdir -p "%(dir)s"' % {'dir': os.path.join(TOOL_DIR, 'apps')})

    if not files.exists(os.path.join(TOOL_DIR, 'apps', 'core')):
        sudo('mkdir -p "%(dir)s"' % {'dir': os.path.join(TOOL_DIR, 'apps', 'core')})

    if not files.exists(os.path.join(TOOL_DIR, 'apps', 'core', 'releases')):
        sudo('mkdir -p "%(dir)s"' % {'dir': os.path.join(TOOL_DIR, 'apps', 'core', 'releases')})

    if not files.exists(os.path.join(TOOL_DIR, 'apps', 'bot')):
        print('Cloning repo')
        sudo('git clone "%(url)s" "%(dir)s"' % {
            'dir': os.path.join(TOOL_DIR, 'apps', 'bot'),
            'url': REPO_URL
        })


def _stop():
    '''
    Internal function, calls jstop on the grid jobs
    '''
    sudo('jstop cbng_bot | true')
    sudo('jstop cbng_core | true')
    sudo('jstop cbng_relay | true')
    sudo('jstop cbng_redis | true')


def _start():
    '''
    Internal function, calls jstart on the grid job jobs
    '''
    if not STAGING:
        sudo('jstart -N cbng_bot   -e /dev/null -o /dev/null -mem 6G %s/apps/bot/bin/run_bot.sh &> /dev/null | true' % TOOL_DIR)
        sudo('jstart -N cbng_relay -e /dev/null -o /dev/null -mem 6G %s/apps/bot/bin/run_relay.sh &> /dev/null | true' % TOOL_DIR)
        sudo('jstart -N cbng_redis -e /dev/null -o /dev/null -mem 6G %s/apps/bot/bin/run_redis.sh &> /dev/null | true' % TOOL_DIR)

    sudo('jstart -N cbng_core  -e /dev/null -o /dev/null -mem 6G %s/apps/core/current/run.sh &> /dev/null | true' % TOOL_DIR)


def _update_code():
    '''
    Clone or pull the git repo into the defined os.path.join(TOOL_DIR, 'apps', 'bot')
    Also updates bigbrotherrc
    '''
    print('Resetting local changes')
    sudo('cd "%(dir)s" && git reset --hard && git clean -fd' %
         {'dir': os.path.join(TOOL_DIR, 'apps', 'bot')})

    print('Updating code')
    sudo('cd "%(dir)s" && git pull origin master' % {'dir': os.path.join(TOOL_DIR, 'apps', 'bot')})

    print('Running composer')
    sudo('cd "%(dir)s" && ./composer.phar install' % {
        'dir': os.path.join(os.path.join(TOOL_DIR, 'apps', 'bot'), 'bot')
    })

    print('Updating crontab')
    sudo('cd "%(dir)s" && cat tools-crontab | crontab -' % {'dir': os.path.join(TOOL_DIR, 'apps', 'bot')})

    print('Updating bigbrotherrc')
    sudo('cd "%(dir)s" && cp -f %(src)s ~/.bigbrotherrc' % {
        'src': ('stage-bigbrotherrc' if STAGING else 'bigbrotherrc'),
        'dir': os.path.join(TOOL_DIR, 'apps', 'bot')
    })


def _update_core():
    '''
    Download the core bins from bintray if they don't exist
    '''
    # Bins
    if not files.exists(os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE)):
        sudo('mkdir -p "%(dir)s"' % {
            'dir': os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE)
        })

    if not files.exists(os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'cluebotng')):
        sudo('cd "%(dir)s" && wget -O cluebotng https://dl.bintray.com/cluebot/cluebotng/$(sha1)s/:cluebotng' % {
            'dir': os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE),
            'sha1': CORE_RELEASE
        })

    if not files.exists(os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'create_ann')):
        sudo('cd "%(dir)s" && wget -O create_ann https://dl.bintray.com/cluebot/cluebotng/$(sha1)s/:create_ann' % {
            'dir': os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE),
            'sha1': CORE_RELEASE
        })

    if not files.exists(os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'create_bayes_db')):
        sudo(
            'cd "%(dir)s" && wget -O create_bayes_db https://dl.bintray.com/cluebot/cluebotng/$(sha1)s/:create_bayes_db' % {
                'dir': os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE),
                'sha1': CORE_RELEASE
            })

    if not files.exists(os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'print_bayes_db')):
        sudo(
            'cd "%(dir)s" && wget -O print_bayes_db https://dl.bintray.com/cluebot/cluebotng/$(sha1)s/:print_bayes_db' % {
                'dir': os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE),
                'sha1': CORE_RELEASE
            })

    # Data files
    if not files.exists(os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'data')):
        sudo('mkdir -p "%(dir)s"' % {
            'dir': os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'data')
        })

    if not files.exists(os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'data', 'main_ann.fann')):
        sudo(
            'cd "%(dir)s" && wget -O main_ann.fann https://dl.bintray.com/cluebot/cluebotng/$(sha1)s/:main_ann.fann' % {
                'dir': os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'data'),
                'sha1': CORE_RELEASE
            })

    if not files.exists(os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'data', 'bayes.db')):
        sudo('cd "%(dir)s" && wget -O bayes.db https://dl.bintray.com/cluebot/cluebotng/$(sha1)s/:bayes.db' % {
            'dir': os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'data'),
            'sha1': CORE_RELEASE
        })

    if not files.exists(os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'data', 'two_bayes.db')):
        sudo('cd "%(dir)s" && wget -O two_bayes.db https://dl.bintray.com/cluebot/cluebotng/$(sha1)s/:two_bayes.db' % {
            'dir': os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'data'),
            'sha1': CORE_RELEASE
        })


def _update_core_configs():
    '''
    Copy the core configs from the bot folder to the core folder
    '''
    sudo('rsync -avr --delete %(src)s %(dest)s' % {
        'dest': os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'conf'),
        'src': os.path.join(TOOL_DIR, 'apps', 'bot', 'conf'),
    })

    sudo('rsync -av --delete %(src)s %(dest)s' % {
        'dest': os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'run.sh'),
        'src': os.path.join(TOOL_DIR, 'apps', 'bot', 'bin', 'run_core.sh'),
    })

    sudo('chmod 750 %s' % os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE, 'run.sh'))
    sudo('ln -sf %(release)s %(current)s' % {
        'release': os.path.join(TOOL_DIR, 'apps', 'core', 'releases', CORE_RELEASE),
        'current': os.path.join(TOOL_DIR, 'apps', 'core', 'current'),
    })


def restart():
    '''
    Stop then start the bot grid task
    '''
    _stop()
    time.sleep(10)
    _start()


def _deploy():
    '''
    Internal deployment function
    '''
    _check_workingdir_clean()
    _check_remote_up2date()

    _setup()
    _update_code()
    _update_core()
    _update_core_configs()
    restart()


def deploy():
    global TOOL_DIR
    TOOL_DIR = '/data/project/cluebotng-staging/'
    env.sudo_user = 'tools.cluebotng-staging'
    _deploy()


def deploy_production():
    global TOOL_DIR, STAGING
    STAGING = False
    TOOL_DIR = '/data/project/cluebotng/'
    env.sudo_user = 'tools.cluebotng'
    _deploy()
