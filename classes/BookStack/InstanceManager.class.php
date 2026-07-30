<?php
/**
 * BookStack MCP Server — Instance Manager
 *
 * Multi-instance configuration registry and client factory.
 *
 * @package    BookstackMCP\BookStack
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace BookStack;

class InstanceManager
{
	/** @var array<string,array> Instance configurations indexed by name. */
	private array $instances;

	/** @var string Name of the current default instance. */
	private string $default;

	/** @var array<string,Client> Cache of Client instances indexed by name. */
	private array $clients = [];

	/**
	 * Create a new InstanceManager.
	 *
	 * @param array<string,array> $instances Instance configurations
	 * @param string              $default   Default instance name
	 */
	public function __construct(array $instances, string $default = '')
	{
		if (empty($instances)) {
			throw new \InvalidArgumentException('At least one instance must be configured.');
		}

		$this->instances = $instances;
		$this->default = $default;
	}

	/**
	 * Create an InstanceManager from a JSON configuration file.
	 *
	 * @param  string $path Path to instances.json
	 * @return self
	 * @throws \RuntimeException If file cannot be read or parsed
	 */
	public static function fromFile(string $path): self
	{
		if (!file_exists($path)) {
			throw new \RuntimeException("Configuration file not found: {$path}");
		}

		$json = file_get_contents($path);
		if ($json === false) {
			throw new \RuntimeException("Failed to read configuration file: {$path}");
		}

		$config = json_decode($json, true);
		if ($config === null && json_last_error() !== JSON_ERROR_NONE) {
			throw new \RuntimeException(
				"Invalid JSON in configuration file {$path}: " . json_last_error_msg()
			);
		}

		$instances = $config['instances'] ?? [];
		$default = $config['default'] ?? '';

		return new self($instances, $default);
	}

	/**
	 * Get a Client for the named instance.
	 *
	 * Clients are cached — the same Client instance is returned
	 * for repeated calls with the same name.
	 *
	 * @param  string $name Instance name (required)
	 * @return Client             BookStack API client
	 * @throws \InvalidArgumentException If instance not found or empty
	 */
	public function getClient(string $name): Client
	{
		if (empty($name)) {
			throw new \InvalidArgumentException('Instance name is required. Pass the instance parameter to every tool call.');
		}

		if (!isset($this->instances[$name])) {
			$available = implode(', ', array_keys($this->instances));
			throw new \InvalidArgumentException(
				"Unknown instance '{$name}'. Available: {$available}"
			);
		}

		if (!isset($this->clients[$name])) {
			$config = $this->instances[$name];

			foreach (['url', 'token_id', 'token_secret'] as $key) {
				if (empty($config[$key])) {
					throw new \InvalidArgumentException(
						"Instance '{$name}': missing required config key: {$key}"
					);
				}
			}

			$this->clients[$name] = new Client(
				$config['url'],
				$config['token_id'],
				$config['token_secret'],
				$config['timeout'] ?? 30
			);
		}

		return $this->clients[$name];
	}

	/**
	 * List all configured instances.
	 *
	 * @return array<string,array{url:string,description:string}>
	 */
	public function listInstances(): array
	{
		$result = [];
		foreach ($this->instances as $name => $config) {
			$result[$name] = [
				'url' => $config['url'] ?? '',
				'description' => $config['description'] ?? '',
			];
		}
		return $result;
	}

	/**
	 * Get the current default instance name.
	 *
	 * @return string
	 */
	public function getDefault(): string
	{
		return $this->default;
	}

	/**
	 * Set the default instance (runtime only, not persisted).
	 *
	 * @param  string $name Instance name
	 * @throws \InvalidArgumentException If instance not found
	 */
	public function setDefault(string $name): void
	{
		if (!isset($this->instances[$name])) {
			$available = implode(', ', array_keys($this->instances));
			throw new \InvalidArgumentException(
				"Unknown instance '{$name}'. Available: {$available}"
			);
		}

		$this->default = $name;
	}

	/**
	 * Check if an instance exists.
	 *
	 * @param  string $name Instance name
	 * @return bool
	 */
	public function hasInstance(string $name): bool
	{
		return isset($this->instances[$name]);
	}

	/**
	 * Get the number of configured instances.
	 *
	 * @return int
	 */
	public function count(): int
	{
		return count($this->instances);
	}
}
