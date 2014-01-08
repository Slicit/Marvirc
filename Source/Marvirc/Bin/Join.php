<?php

namespace Marvirc\Bin {

use Hoa\Console;
use Hoa\Irc;
use Hoa\Socket;
use Hoa\File\Finder;

class Join extends Console\Dispatcher\Kit {

    protected $options = array(
        array('socket',   Console\GetOption::REQUIRED_ARGUMENT, 's'),
        array('username', Console\GetOption::REQUIRED_ARGUMENT, 'u'),
        array('channel',  Console\GetOption::REQUIRED_ARGUMENT, 'c'),
        array('password', Console\GetOption::REQUIRED_ARGUMENT, 'p'),
        array('verbose',  Console\GetOption::NO_ARGUMENT,       'v'),
        array('help',     Console\GetOption::NO_ARGUMENT,       'h'),
        array('help',     Console\GetOption::NO_ARGUMENT,       '?')
    );



    public function main ( ) {

        $socket   = 'tcp://chat.freenode.org:6667';
        $username = null;
        $channel  = null;
        $password = null;
        $verbose  = false;

        while(false !== $c = $this->getOption($v)) switch($c) {

            case 's':
                $socket = $v;
              break;

            case 'u':
                $username = $v;
              break;

            case 'c':
                $channel = $v;
              break;

            case 'p':
                $password = $v;
              break;

            case 'v':
                $verbose = $v;
              break;

            case 'h':
            case '?':
                return $this->usage();
              break;

            case '__ambiguous':
                $this->resolveOptionAmbiguity($v);
              break;
        }

        if(empty($username) || empty($channel))
            return $this->usage();

        $self   = $this;
        $client = new Irc\Client(new Socket\Client($socket));

        // Open.
        $client->on('open', function ( $bucket ) use ( $username, $channel )  {

            $bucket->getSource()->join($username, $channel);

            return;
        });

        // Join.
        $verbose and
        $client->on('join', function ( $bucket ) use ( $channel ) {

            echo '[', $channel, '] Joined!', "\n";

            return;
        });

        $client->on('private-message', function ( $bucket ) use ( $self ) {

            $data = $bucket->getData();
            $bucket->getSource()->say(
                $self->getAnswer('PrivateMessage', $data),
                $data['from']['nick']
            );

            return;
        });

        $client->on('mention', function ( $bucket ) use ( $self ) {

            $data = $bucket->getData();
            $bucket->getSource()->say(
                $data['from']['nick'] . ': ' .
                $self->getAnswer('Mention', $data)
            );

            return;
        });

        // Kick.
        $verbose and
        $client->on('kick', function ( $bucket ) use ( $channel ) {

            echo '[', $channel, '] Kicked :-(.', "\n";

            return;
        });

        // Other messages.
        $verbose and
        $client->on('other-message', function ( $bucket ) use ( $channel ) {

            $data = $bucket->getData();

            echo '[', $channel, '] ';
            Console\Cursor::colorize('foreground(green)');
            echo $data['line'];
            Console\Cursor::colorize('normal');
            echo "\n";

            return;
        });

        // Error.
        $client->on('error', function ( $bucket ) use ( $channel ) {

            $data = $bucket->getData();

            echo '[', $channel, '] ';
            Console\Cursor::colorize('foreground(red)');
            echo $data['exception']->raise(true);
            Console\Cursor::colorize('normal');
            echo "\n";

            return;
        });

        // Here we go.
        $client->run();

        return;
    }

    public function usage ( ) {

        echo 'Usage   : join <options>', "\n",
             'Options :', "\n",
             $this->makeUsageOptionsList(array(
                 's'    => 'Socket (default: tcp://chat.freenode.org:6667).',
                 'u'    => 'Username.',
                 'c'    => 'Channel (with the leading #).',
                 'p'    => 'Password.',
                 'v'    => 'Verbose.',
                 'help' => 'This help.'
             ));

        return;
    }

    public function getAnswer ( $messageType, Array $data ) {

        static $_cache = array();

        if(!isset($_cache[$messageType])) {

            $path = dirname(__DIR__) . DS . 'Action' . DS . $messageType;
            $finder = new Finder();
            $finder->in($path)
                   ->files()
                   ->name('#\.php$#');

            $collect = array();

            foreach($finder as $entry) {

                $classname = 'Marvirc\Action\\' . $messageType . '\\' .
                             substr($entry->getBasename(), 0, -4);
                $collect[$classname] = $classname::getPattern();
            }

            $_cache[$messageType] = $collect;
        }

        foreach($_cache[$messageType] as $classname => $pattern)
            if(0 !== preg_match($pattern, $data['message']))
                return $classname::compute($data);

        return 'What?';
    }
}

}