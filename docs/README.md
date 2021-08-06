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
```
