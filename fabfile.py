import subprocess
import sys
import os.path
from fabric.api import run, env, sudo
from fabric.contrib import files
import time

LOGIN_HOST = 'tools-login.wmflabs.org'
DEST_TOOL = 'cluebot'
DEST_DIR = '/data/project/%s/cluebotng' % DEST_TOOL
LOG_DIR = '/data/project/%s/logs' % DEST_TOOL
REPO_URL = 'https://github.com/DamianZaremba/cluebotng.git'

# Internal settings
env.hosts = [LOGIN_HOST]
env.use_ssh_config = True
env.sudo_user = 'tools.%s' % DEST_TOOL
env.sudo_prefix = "/usr/bin/sudo -ni"


def check_workingdir_clean():
    p = subprocess.Popen(['git', 'diff', '--exit-code'],
                         stdout=subprocess.PIPE,
                         stderr=subprocess.PIPE)
    p.communicate()

    if p.returncode != 0:
        print('There are local, uncommited changes.')
        print('Refusing to deploy.')
        sys.exit(1)


def check_remote_up2date():
    p = subprocess.Popen(['git', 'ls-remote', REPO_URL, 'master'],
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


def setup():
    PARENT_DEST_DIR = os.path.dirname(DEST_DIR)
    if not files.exists(PARENT_DEST_DIR):
        sudo('mkdir -p "%(dir)s"' % {'dir': PARENT_DEST_DIR})

    if not files.exists(DEST_DIR):
        print('Cloning repo')
        sudo('git clone "%(url)s" "%(dir)s"' %
             {'dir': DEST_DIR, 'url': REPO_URL})


def stop():
    sudo('jstop cbng_bot | true')
    sudo('jstop cbng_core | true')
    sudo('jstop cbng_relay | true')


def start():
    sudo('jsub -once -continuous -N cbng_relay -mem 6G' +
         ' -e %s/cbng_relay.err ' % LOG_DIR +
         ' -o %s/cbng_relay.out ' % LOG_DIR +
         ' %s/bin/run_relay.sh | true' % DEST_DIR)

    sudo('jsub -once -continuous -N cbng_core -mem 6G' +
         ' -e %s/cbng_core.err ' % LOG_DIR +
         ' -o %s/cbng_core.out ' % LOG_DIR +
         ' %s/bin/run_core.sh | true' % DEST_DIR)

    time.sleep(1)
    sudo('jsub -once -continuous -N cbng_bot -mem 6G' +
         ' -e %s/cbng_bot.err ' % LOG_DIR +
         ' -o %s/cbng_bot.out ' % LOG_DIR +
         ' %s/bin/run_bot.sh | true' % DEST_DIR)


def update_code():
    print('Resetting local changes')
    sudo('cd "%(dir)s" && git reset --hard && git clean -fd' %
         {'dir': DEST_DIR})

    print('Updating code')
    sudo('cd "%(dir)s" && git pull origin master' % {'dir': DEST_DIR})

   print('Running composer')
    sudo('cd "%(dir)s" && ./composer.phar install' % {
        'dir': os.path.join(DEST_DIR, 'bot')
    })

    print('Updating crontab')
    sudo('cd "%(dir)s" && cat tools-crontab | crontab -' % {'dir': DEST_DIR})


def restart():
    stop()
    time.sleep(1)
    start()


def deploy():
    check_workingdir_clean()
    check_remote_up2date()

    setup()
    update_code()
    restart()
