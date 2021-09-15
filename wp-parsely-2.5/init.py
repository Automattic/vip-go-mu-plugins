#!/usr/bin/env python
'''
Initializes the wp-parsely git code repo locally and configures git to add
WordPress SVN repo.
'''
from __future__ import print_function
from subprocess import check_output, check_call, call
import os
import sys


def latest_revision(svn_repo):
    revision = check_output('svn log -r 1:HEAD --limit 1 {}'.format(svn_repo), shell=True)
    revision = revision.split('\n')
    if len(revision) < 2:
        return

    revision = revision[1].split(' ')[0]
    return revision


def main():
    if os.path.exists('.git'):
        print('Found .git folder in current directory, this script should not '
              'be run inside a git repository (did you already git clone?).',
              file=sys.stderr)
        sys.exit(1)

    plugin_name = 'wp-parsely'
    svn_repo = 'http://plugins.svn.wordpress.org/{}/'.format(plugin_name)
    git_repo = 'git@github.com:Parsely/wp-parsely.git'
    print('Determining latest revision for {}'.format(plugin_name))
    revision = latest_revision(svn_repo)
    if not revision:
        print('Could not find a revision number for svn repo.',
              file=sys.stderr)
        sys.exit(1)

    check_call('git svn clone --no-minimize-url -s -{} {}'.format(revision, svn_repo),
               shell=True)

    print('Fetching all commits from SVN repo')
    check_call('git svn fetch --log-window-size 10000', cwd=plugin_name,
               shell=True)
    check_call('git svn rebase', cwd=plugin_name, shell=True)

    print('Initializing git')
    check_call('git remote add origin {}'.format(git_repo), cwd=plugin_name,
               shell=True)
    print('git origin {} added'.format(git_repo))
    print('Done.')



if __name__ == '__main__':
    main()
