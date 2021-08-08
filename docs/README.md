# Nette Monolog

Monolog logger integration for Nette

## Content

TODO

```neon
extensions:
	monolog: OriNette\Monolog\DI\MonologExtension

monolog:

	# Individual channels
	# Each is registered as a service
	channels:

		# Channel name + minimal config
		<ch1>: []

		# Channel name + full config
		<ch2>:

			# Autowire channel service
			# Not autowired by default
			# true | false | class-string<Monolog\Logger>
			autowired: true

	# Handlers register to all channels
	# - unless channel specifies allowed/forbidden handlers
	handlers:

		# Handler name + minimal config
		<h1>:
			service: @h1Service

		# Handler name + full config
		<h2>:

			# Handler service
			service: @h2Service

			# Enable handler?
			# Enabled by default
			# true | false
			enabled: %debugMode%

	# Processors registered to all channels
	# - unless channel specifies allowed/forbidden processors
	# Handlers have to register processors on their own
	processors:
		<p1>: @p1Service

	# Bridges to other loggers / error handlers
	bridge:
		# Log from Tracy to Monolog channels
		# array<string>
		fromTracy: [<ch1>, <ch2>] # Log from Tracy to <ch1> and <ch2>

		# Log from Monolog channels to Tracy
		# true | false (disabled by default)
		# When enabled handler 'tracy' is added and can be configured
		# - skip the 'service' key, it's preconfigured
		toTracy: true
```
