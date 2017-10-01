<?php

/**
 * Copyright 2017 The WildPHP Team
 *
 * You should have received a copy of the MIT license with the project.
 * See the LICENSE file for more information.
 */

namespace WildPHP\Modules\Moderation;

use WildPHP\Core\Channels\Channel;
use WildPHP\Core\Channels\ChannelCollection;
use WildPHP\Core\Channels\ValidChannelNameParameter;
use WildPHP\Core\Commands\Command;
use WildPHP\Core\Commands\CommandHandler;
use WildPHP\Core\Commands\CommandHelp;
use WildPHP\Core\Commands\JoinedChannelParameter;
use WildPHP\Core\Commands\NumericParameter;
use WildPHP\Core\Commands\ParameterStrategy;
use WildPHP\Core\Commands\StringParameter;
use WildPHP\Core\ComponentContainer;
use WildPHP\Core\Configuration\Configuration;
use WildPHP\Core\Connection\Queue;
use WildPHP\Core\EventEmitter;
use WildPHP\Core\Modules\BaseModule;
use WildPHP\Core\Users\User;
use Yoshi2889\Tasks\CallbackTask;
use Yoshi2889\Tasks\TaskController;

class Moderation extends BaseModule
{
	/**
	 * @var TaskController
	 */
	protected $taskController;
	/**
	 * ModerationCommands constructor.
	 *
	 * @param ComponentContainer $container
	 */
	public function __construct(ComponentContainer $container)
	{
		$this->taskController = new TaskController($container->getLoop());
		$this->setContainer($container);

		EventEmitter::fromContainer($container)
			->on('irc.line.in.376', [$this, 'registerCommands']);
	}

	public function registerCommands()
	{
		$container = $this->getContainer();
		$channelPrefix = Configuration::fromContainer($container)['serverConfig']['chantypes'];

		CommandHandler::fromContainer($container)->registerCommand('kick',
			new Command(
				[$this, 'kickCommand'],
				new ParameterStrategy(1, -1, [
					'nickname' => new StringParameter(),
					'reason' => new StringParameter()
				], true),
				new CommandHelp([
					'Kicks the specified user from the channel. Usage: kick [nickname] ([reason])'
				]),
				'kick'
			));

		CommandHandler::fromContainer($container)->registerCommand('remove',
			new Command(
				[$this, 'removeCommand'],
				new ParameterStrategy(1, -1, [
					'nickname' => new StringParameter(),
					'reason' => new StringParameter()
				], true),
				new CommandHelp([
					'Requests a user to leave the channel. Usage: remove [nickname] ([reason])'
				]),
				'remove'
			));

		CommandHandler::fromContainer($container)->registerCommand('topic',
			new Command(
				[$this, 'topicCommand'],
				[
					new ParameterStrategy(2, -1, [
						'channel' => new JoinedChannelParameter(ChannelCollection::fromContainer($container)),
						'message' => new StringParameter()
					], true),
					new ParameterStrategy(1, -1, [
						'message' => new StringParameter()
					], true),
				],
				new CommandHelp([
					'Changes the topic for the specified channel. Usage: topic ([channel]) [message]'
				]),
				'topic'
			));

		CommandHandler::fromContainer($container)->registerCommand('kban',
			new Command(
				[$this, 'kbanCommand'],
				[
					new ParameterStrategy(3, -1, [
						'nickname' => new StringParameter(),
						'minutes' => new NumericParameter(),
						'redirect' => new ValidChannelNameParameter($channelPrefix),
						'reason' => new StringParameter()
					], true),
					new ParameterStrategy(3, -1, [
						'nickname' => new StringParameter(),
						'redirect' => new ValidChannelNameParameter($channelPrefix),
						'minutes' => new NumericParameter(),
						'reason' => new StringParameter()
					], true),
					new ParameterStrategy(2, -1, [
						'nickname' => new StringParameter(),
						'redirect' => new ValidChannelNameParameter($channelPrefix),
						'reason' => new StringParameter()
					], true),
					new ParameterStrategy(2, -1, [
						'nickname' => new StringParameter(),
						'minutes' => new NumericParameter(),
						'reason' => new StringParameter()
					], true),
					new ParameterStrategy(1, -1, [
						'nickname' => new StringParameter(),
						'reason' => new StringParameter()
					], true)
				],
				new CommandHelp([
					'Kicks the specified user from the channel and adds a ban. Usage: ban [nickname] ([minutes]) ([redirect channel])',
					'Leave minutes empty or pass 0 minutes for an indefinite ban.'
				]),
				'kban'
			));

		CommandHandler::fromContainer($container)->registerCommand('ban',
			new Command(
				[$this, 'banCommand'],
				[
					new ParameterStrategy(2, 2, [
						'nickname' => new StringParameter(),
						'redirect' => new ValidChannelNameParameter($channelPrefix)
					]),
					new ParameterStrategy(3, 3, [
						'nickname' => new StringParameter(),
						'minutes' => new NumericParameter(),
						'redirect' => new ValidChannelNameParameter($channelPrefix)
					]),
					new ParameterStrategy(2, 2, [
						'nickname' => new StringParameter(),
						'minutes' => new NumericParameter()
					])
				],
				new CommandHelp([
					'Bans the specified user from the channel. Usage: ban [nickname] ([minutes]) ([redirect channel])',
					'Leave minutes empty or pass 0 minutes for an indefinite ban.'
				]),
				'ban'
			));

		CommandHandler::fromContainer($container)->registerCommand('banhost',
			new Command(
				[$this, 'banhostCommand'],
				[
					new ParameterStrategy(2, 2, [
						'hostname' => new StringParameter(),
						'redirect' => new ValidChannelNameParameter($channelPrefix)
					]),
					new ParameterStrategy(3, 3, [
						'hostname' => new StringParameter(),
						'minutes' => new NumericParameter(),
						'redirect' => new ValidChannelNameParameter($channelPrefix)
					]),
					new ParameterStrategy(2, 2, [
						'hostname' => new StringParameter(),
						'minutes' => new NumericParameter()
					])
				],
				new CommandHelp([
					'Bans the specified host from the channel. Usage: banhost [hostname] ([minutes]) ([redirect channel])',
					'Leave minutes empty or pass 0 minutes for an indefinite ban.'
				]),
				'ban'
			));

		CommandHandler::fromContainer($container)->registerCommand('mode',
			new Command(
				[$this, 'modeCommand'],
				new ParameterStrategy(1, 2, [
					'modes' => new StringParameter(),
					'entities' => new StringParameter()
				]),
				new CommandHelp([
					'Changes mode for a specified entity. Usage: mode [mode(s)] ([entity/ies])'
				]),
				'mode'
			));
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param $args
	 * @param ComponentContainer $container
	 */
	public function kickCommand(Channel $source, User $user, $args, ComponentContainer $container)
	{
		$nickname = (string) $args['nickname'];
		$message = !empty($args['reason']) ? $args['reason'] : $nickname;
		$userObj = $source->getUserCollection()
			->findByNickname($nickname);

		if ($nickname == Configuration::fromContainer($container)['currentNickname'])
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'I refuse to hurt myself!');

			return;
		}

		if (!$userObj)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': This user is currently not in the channel.');

			return;
		}

		Queue::fromContainer($container)
			->kick($source->getName(), $nickname, $message);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param $args
	 * @param ComponentContainer $container
	 */
	public function removeCommand(Channel $source, User $user, $args, ComponentContainer $container)
	{
		$nickname = (string) $args['nickname'];
		$message = !empty($args['reason']) ? $args['reason'] : $nickname;
		$userObj = $source->getUserCollection()
			->findByNickname($nickname);

		if ($nickname == Configuration::fromContainer($container)['currentNickname'])
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'What? I can\'t leave...!');

			return;
		}

		if (!$userObj)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': This user is currently not in the channel.');

			return;
		}

		Queue::fromContainer($container)
			->remove($source->getName(), $nickname, $message);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param $args
	 * @param ComponentContainer $container
	 */
	public function topicCommand(Channel $source, User $user, $args, ComponentContainer $container)
	{
		/** @var Channel $channel */
		$channel = !empty($args['channel']) ? $args['channel'] : $source;
		$channelName = $channel->getName();
		$message = $args['message'];

		Queue::fromContainer($container)
			->topic($channelName, $message);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param $args
	 * @param ComponentContainer $container
	 */
	public function kbanCommand(Channel $source, User $user, $args, ComponentContainer $container)
	{
		$nickname = (string) $args['nickname'];
		$message = !empty($args['reason']) ? $args['reason'] : $nickname;
		$minutes = !empty($args['minutes']) ? (int) $args['minutes'] : 0;
		$redirect = !empty($args['redirect']) ? $args['redirect'] : '';
		$userObj = $source->getUserCollection()
			->findByNickname($nickname);

		if ($nickname == Configuration::fromContainer($container)['currentNickname'])
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'I refuse to hurt myself!');

			return;
		}

		if (!$userObj)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': This user is currently not in the channel.');

			return;
		}

		$time = 60 * $minutes;
		$this->banUser($source, $userObj, $container, $time, $redirect);

		Queue::fromContainer($container)
			->kick($source->getName(), $nickname, $message);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param $args
	 * @param ComponentContainer $container
	 */
	public function banCommand(Channel $source, User $user, $args, ComponentContainer $container)
	{
		$nickname = (string) $args['nickname'];
		$minutes = !empty($args['minutes']) ? (int) $args['minutes'] : 0;
		$redirect = !empty($args['redirect']) ? $args['redirect'] : '';
		$userObj = $source->getUserCollection()
			->findByNickname($nickname);

		if ($nickname == Configuration::fromContainer($container)['currentNickname'])
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), 'I refuse to hurt myself!');

			return;
		}

		if (!$userObj)
		{
			Queue::fromContainer($container)
				->privmsg($source->getName(), $user->getNickname() . ': This user is currently not in the channel.');

			return;
		}

		$time = 60 * $minutes;
		$this->banUser($source, $userObj, $container, $time, $redirect);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param $args
	 * @param ComponentContainer $container
	 */
	public function banhostCommand(Channel $source, User $user, $args, ComponentContainer $container)
	{
		$hostname = (string) $args['hostname'];
		$minutes = !empty($args['minutes']) ? (int) $args['minutes'] : 0;
		$redirect = !empty($args['redirect']) ? $args['redirect'] : '';
		$time = 60 * $minutes;
		$this->applyBanmask($source, $hostname, $container, $time, $redirect);
	}

	/**
	 * @param Channel $source
	 * @param User $user
	 * @param $args
	 * @param ComponentContainer $container
	 */
	public function modeCommand(Channel $source, User $user, $args, ComponentContainer $container)
	{
		$modes = $args['modes'];
		$target = !empty($args['entities']) ? $args['entities'] : '';

		Queue::fromContainer($container)
			->mode($source->getName(), $modes, [$target]);
	}

	/**
	 * @param Channel $source
	 * @param User $userObj
	 * @param ComponentContainer $container
	 * @param int $offset
	 * @param string $redirect
	 */
	protected function banUser(Channel $source, User $userObj, ComponentContainer $container, int $offset, string $redirect = '')
	{
		$hostname = $userObj->getHostname();
		$username = $userObj->getUsername();
		$ban = '*!' . $username . '@' . $hostname;

		$this->applyBanmask($source, $ban, $container, $offset, $redirect);
	}

	/**
	 * @param Channel $source
	 * @param string $banmask
	 * @param ComponentContainer $container
	 * @param int $offset
	 * @param string $redirect
	 */
	protected function applyBanmask(Channel $source, string $banmask, ComponentContainer $container, int $offset, string $redirect = '')
	{
		if (!empty($redirect))
			$banmask .= '$' . $redirect;

		if ($offset != 0)
		{
			$args = [$source, $banmask, $container];
			$task = new CallbackTask([$this, 'removeBan'], $offset, $args);
			$this->taskController->add($task);
		}

		Queue::fromContainer($container)
			->mode($source->getName(), '+b', [$banmask]);
	}

	/**
	 * @param Channel $source
	 * @param string $banmask
	 * @param ComponentContainer $container
	 */
	public function removeBan(Channel $source, string $banmask, ComponentContainer $container)
	{
		Queue::fromContainer($container)
			->mode($source->getName(), '-b', [$banmask]);
	}

	/**
	 * @return string
	 */
	public static function getSupportedVersionConstraint(): string
	{
		return '^3.0.0';
	}
}