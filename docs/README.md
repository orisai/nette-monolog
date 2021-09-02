# Nette Monolog

[Monolog](https://github.com/Seldaek/monolog) logger integration for Nette

## Content

- [Setup](#setup)
- [Configuring logger](#configuring-logger)
- [Logging messages](#logging-messages)
- [Channels](#channels)
- [Handlers](#handlers)
- [Log levels](#log-levels)
- [Log record](#log-record)
- [Processors](#processors)
- [Formatters](#formatters)
- [Integrations](#integrations)
	- [Tracy](#tracy)
	- [Logtail](#logtail)
- [Efficiency](#efficiency)
- [Static logger access](#static-logger-access)

## Setup

Install with [Composer](https://getcomposer.org)

```sh
composer require orisai/nette-monolog
```

Register extension

```neon
extensions:
	monolog: OriNette\Monolog\DI\MonologExtension
```

Configure debug mode. How is debug mode used is explained in [log levels](#log-levels).

```neon
monolog:

	# true | false
	debug: %debugMode%
```

## Configuring logger

In order to start logging you have to create a channel (logger service instance) and at least one handler the channel
will log into. Then you can continue to [logging messages](#logging-messages).

For more details how to configure your logger check [channels](#channels) and [handlers](#handlers).

```neon
monolog:
	channels:
		example:
			autowired: true

	handlers:
		file:
			service: Monolog\Handler\RotatingFileHandler(%tempDir%/monolog.txt)
```

## Logging messages

To log messages, just request instance of `Psr\Log\LoggerInterface` from the DIC and use one of logger methods. Every
level has its own identically named method like `critical()` but you may also use `log()` to specify level dynamically.

```php
use Psr\Log\LoggerInterface;

class Example
{

	private LoggerInterface $logger;

	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	public function doSomething(): void
	{
		$this->logger->alert('Did something! Be proud of yourself, you are doing your best.');
	}

}
```

To add machine-readable info to message, use context parameter.

```php
$this->logger->info('New user registered', [
	'user' => $user->id,
]);
```

If you want log an exception (or any subclass of `Throwable`), always use the `exception` field, it's expected by
multiple handlers. However, it should not be a frequent case because our [Tracy integration](#tracy) already handles
that for you via `toTracy` option.

```php
$this->logger->error('Oh no! It\'s broken', [
	'exception' => new Exception(),
]);
```

## Channels

Each logger service is called a channel. Channels differentiate from each other by unique name which is added to every
log record and by configuration - allowed handlers, processors and by name of autowired class. For most applications
single *main* channel is enough but in more complex and huge applications you may set up logger for each use case
individually.

```neon
monolog:

	# Individual channels
	# Each is registered as a service
	channels:

		main:
			# Autowire channel service
			# true|false|class-string<Monolog\Logger>
			# Default: false
			autowired: true

		# Channel name + minimal config
		other: []
```

Service auto-wiring via `autowired` option is disabled by default and can be either set to `true` to allow auto-wiring
via `Psr\Log\LoggerInterface` and `Monolog\Logger` or set to a name of class which extends `Monolog\Logger` to auto-wire
by that class.

Check [handlers](#handlers) and [processors](#processors) documentation to learn how to allow or forbid them for
specific channel.

## Handlers

Handlers are responsible for writing log record to storage and channel writes into all of them unless successful handler
do not enable bubbling (see below) or refuses to handle record (because record level is too low).

Check [Monolog documentation](https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md#handlers)
for built-in handlers.

Each handler has to define `service` key - either referencing existing service or defining new, with same syntax as in
root `services` section. Service is expected to return `Monolog\Handler\HandlerInterface`.

```neon
monolog:

	# Handlers registered to all channels
	# - unless channel specifies allowed/forbidden handlers
	handlers:

		# Handler name + config
		file:
			# Handler service
			# @reference|service definition
			service: Monolog\Handler\RotatingFileHandler(%tempDir%/monolog.txt)

		redis:
			service: Monolog\Handler\RedisHandler(@redis.client, logs)
```

Handlers can be disabled by config option for example to enable console handler only in console mode.

```neon
monolog:
	handlers:

		console:
			# Enable handler?
			# true|false
			# Default: true
			enabled: %consoleMode%

			service: Monolog\Handler\PHPConsoleHandler
```

After handler successfully handle log record it is passed to next handler in order. You may change this behavior by
disabling bubbling. With bubbling disabled only when handler don't handle record then it is passed to next handler.
Usually handler don't handle record when its level is too low but other reasons may exist, e.g. external service
timeout.

In following example, `StreamHandler` don't handle record unless `MailHandler` failed:

```neon
monolog:
	handlers:

		mail:
			# Should successful handler pass record to next handler?
			# If not then successful handler is the last one which logs the record
			# true|false
			# Default: true
			bubble: false

			service: Monolog\Handler\NativeMailerHandler(...)

		stream:
			service: Monolog\Handler\StreamHandler('file://%logDir%/monolog.txt')
```

Handlers are registered to all channels. To add only explicitly allowed handlers, use `handlers > allowed` channel
option. To add all handlers except forbidden, use `handlers > forbidden` channel option.

```neon
monolog:
	channels:
		example:

			# Allowed/forbidden handlers
			# - unless specified all are used
			# - only one of these options can be used
			handlers:

				# array<string>
				# Default: []
				allowed:
					- file
					- redis

				# array<string>
				# Default: []
				forbidden:
					- file
```

## Log levels

Log level describes importance of message and also affects whether handler handles message. e.g. handler which is set to
lowest level (`debug`) handles all messages, handler with highest level (`emergency`) handles only top priority
messages.

By default all handlers handle `debug` messages in debug mode and `warning` messages when debug is disabled.

```neon
monolog:

	# Affects whether level > debug or level > production is used
	# - used for both root and handler `level` option
	# true|false
	# Required
	debug: %debugMode%
```

It is possible to change minimal level handled by all handlers:

```neon
monolog:

	# Default log level
	# Minimal level of message handler should handle
	# Psr\Log\LogLevel::*
	level:
		# With debug mode enabled
		# Default: debug
		debug: debug
		# Without debug mode enabled
		# Default: warning
		production: warning
```

Or change level only for specific handler:

```neon
monolog:
	handlers:
		example:

			# Minimal level of message handler should handle
			# Psr\Log\LogLevel::*|null
			level:
				# With debug mode enabled
				debug: debug
				# Without debug mode enabled
				production: warning
```

To better understand when should be individual levels used,
check [Monolog documentation](https://github.com/Seldaek/monolog/blob/main/doc/01-usage.md#log-levels)

## Log record

Record is an array, composed of message, level and context from [logged message](#logging-messages), date and time when
message was logged and extra fields from [processors](#processors).

Check [Monolog documentation](https://github.com/Seldaek/monolog/blob/main/doc/message-structure.md) for complete
structure description. Check also [formatters](#formatters) for explanation how is record formatted before it is written
to storage.

## Processors

Processors are used to add useful info to record to `extra` field and are registered to each channel or to specific
handler.

Check [Monolog documentation](https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md#processors)
for built-in processors.

Each processor must either reference existing service or define new, with same syntax as in root `services` section.
Service is expected to return `Monolog\Handler\HandlerInterface`.

```neon
monolog:

	# Processors registered to all channels
	# - unless channel specifies allowed/forbidden processors
	# Handlers have to register processors on their own
	processors:
		# Processor service
		# @reference|service definition
		git: Monolog\Processor\GitProcessor

		web: Monolog\Processor\WebProcessor
```

Processors are registered to all channels. To add only explicitly allowed processors, use `processors > allowed` channel
option. To add all processors except forbidden, use `processors > forbidden` channel option.

```neon
monolog:
	channels:
		example:

			# Allowed/forbidden processors
			# - unless specified all are used
			# - only one of these options can be used
			processors:

				# array<string>
				# Default: []
				allowed:
					- git
					- web

				# array<string>
				# Default: []
				forbidden:
					- git
```

Register processor to single handler, instead of channel:

```neon
monolog:
	handlers:
		example:

			# Processors registered to this handler only
			processors:
				# @reference|service definition
				- Monolog\Processor\TagProcessor(['something special'])
```

## Formatters

Formatters format [log record](#log-record) before it is written into storage.

Check [Monolog documentation](https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md#formatters)
for built-in formatters.

Don't change formatter unless you have to. Default ones are usually good enough and handlers may not work correctly
without them (e.g. `MongoDBHandler` without `MongoDBFormatter`). Some may not be compatible because return type of
formatters is not enforced and handlers may expect either string or array (e.g. `NewRelicHandler`
with `JsonFormatter`). So use with caution.

Formattable handlers usually extends `AbstractProcessingHandler` or use `FormattableHandlerTrait` and can be registered
like this:

```neon
monolog:
	handlers:

		file:
			service:
				factory: Monolog\Handler\RotatingFileHandler(%tempDir%/monolog.txt)
				setup:
					- setFormatter(Monolog\Formatter\LineFormatter("%%datetime%% > %%level_name%% > %%message%% %%context%% %%extra%%"))
```

Be aware that parameters in neon use same `%name%` syntax as `LineFormatter` and you have to escape percents by doubling
them: `%%name%%`

## Integrations

Monolog is easily extensible and
provides [many integrations](github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md) itself. If
you are seeking for something more (mainly Nette-related), check our integrations:

### Tracy

[Tracy](https://github.com/nette/tracy/) debugger integration allows you to log messages to Tracy from Monolog channels
and from Tracy to Monolog channels.

For logging *from* Tracy *to* Monolog specify all the channels Tracy should log into.

Be aware that *fromTracy* bridge works only for `Tracy\Debugger::log()`. Using `Tracy\ILogger->log()` will result into
logging only by enabled Monolog channels. This limitation can be simply worked around by enabling *toTracy* bridge.

```neon
monolog:
	bridge:

		# Log from Tracy to Monolog channels
		# array<string>
		# Default: []
		fromTracy:
			- channelName
			- anotherChannelName
```

For logging *to* Tracy *from* Monolog, enable `toTracy` option.

Under the hood extension registers [handler](#handlers) with reserved name `tracyLogger`. This handler can be configured
as any other (except the preset `service` key) so you can e.g. allow or forbid this handler for specific channels.

```neon
monolog:
	bridge:

		# Log from Monolog channels to Tracy
		# true|false
		# Default: false
		toTracy: true
```

Tracy don't have same error levels as Monolog, following comparison table should help you understand how levels are
mapped:

<table class="tg">
<thead>
	<tr>
		<th>Tracy</th>
		<th>Monolog</th>
	</tr>
</thead>
<tbody>
	<tr>
		<td rowspan="3">critical</td>
		<td>emergency</td>
	</tr>
	<tr>
		<td>alert</td>
	</tr>
	<tr>
		<td>critical</td>
	</tr>
	<tr>
		<td>error</td>
		<td>error</td>
	</tr>
	<tr>
		<td rowspan="2">warning</td>
		<td>warning</td>
	</tr>
	<tr>
		<td>notice</td>
	</tr>
	<tr>
		<td>info</td>
		<td>info</td>
	</tr>
	<tr>
		<td>debug</td>
		<td>debug</td>
	</tr>
</tbody>
</table>

### Logtail

[Logtail](https://logtail.com) is a fast and flexible logging service with great UX. While they have their own Monolog
integration we felt need for something more robust. Our handler utilizes symfony/http-client and lazy-writes logs in
batches to provide the best possible experience.

```neon
monolog:
	handlers:
		logtail:
			service: OriNette\Monolog\Bridge\Logtail\LogtailHandler(
				OriNette\Monolog\Bridge\Logtail\LogtailClient::create(%logtail.token%)
			)

parameters:
	logtail:
		token: <YOUR_LOGTAIL_TOKEN>
```

## Efficiency

Some handlers log in batches instead of logging every message individually. This greatly improves overall performance
for usual use cases but also increases memory usage for long-running task. To ensure your tasks count with that and
flush records occasionally, use `OriNette\Monolog\LogFlusher`. It has available same `reset()` and `close()` methods as
`Monolog\Logger` but also ensures method is called for all used loggers.

```php
use App\Core\BusinessEventsLogger;
use OriNette\Monolog\LogFlusher;
use Psr\Log\LoggerInterface;

class Example
{

	private LoggerInterface $mainLogger;

	private BusinessEventsLogger $businessEventsLogger;

	private LogFlusher $logFlusher;

	public function __construct(
		LoggerInterface $mainLogger,
		BusinessEventsLogger $businessEventsLogger,
		LogFlusher $logFlusher,
	)
	{
		$this->mainLogger = $mainLogger;
		$this->businessEventsLogger = $businessEventsLogger;
		$this->logFlusher = $logFlusher;
	}

	public function doSomething(): void
	{
		// A lot of logging
		$this->mainLogger->info('Uh, I am logging a lot.');
		$this->businessEventsLogger->emergency('Yikes! We are doomed.');
		// ...
		// Flush instantiated loggers
		$this->logFlusher->reset();
	}

}
```

To enable buffering for handlers that may benefit from it and do not support buffering out of the box you may
use `BufferHandler` or `FingersCrossedHandler`.

## Static logger access

When DI cannot be used, mostly in legacy code, logger can be accessed statically via `LoggerGetter`.

To enable it, configure which channel should static logger use:

```neon
monolog:
	staticLogger: main

	channels:
		main: []
```

Then just get logger and use it as usual:

```php
use OriNette\Monolog\LoggerGetter;

$logger = LoggerGetter::get();
$logger->info('Fart travels from your body at ~11 km/h.');
```
