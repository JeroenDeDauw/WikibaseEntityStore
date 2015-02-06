<?php

namespace Wikibase\EntityStore\Config;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\ChainCache;
use InvalidArgumentException;
use Mediawiki\Api\MediawikiApi;
use RuntimeException;
use Symfony\Component\Config\Definition\Processor;
use Wikibase\EntityStore\Api\ApiEntityStore;
use Wikibase\EntityStore\Cache\CachedEntityStore;
use Wikibase\EntityStore\EntityStore;
use Wikibase\EntityStore\MongoDB\MongoDBEntityStore;

class EntityStoreFromConfigurationBuilder {

	/**
	 * @param string $configurationFileName
	 * @return EntityStore
	 */
	public function buildEntityStore( $configurationFileName ) {
		$config = $this->parseConfiguration( $configurationFileName );

		$store = $this->buildStore( $config );

		if( array_key_exists( 'cache', $config ) ) {
			$cache = $this->buildCache( $config['cache'] );

			if( $cache !== null ) {
				return new CachedEntityStore( $store, $cache );
			}
		}

		return $store;
	}

	private function buildStore( $config ) {
		switch( $config['backend'] ) {
			case 'api':
				return new ApiEntityStore( new MediawikiApi( $config['api']['url'] ) );
			case 'mongodb':
				return new MongoDBEntityStore( $this->getMongoDbCollection( $config['mongodb'] ) );
			default:
				throw new InvalidArgumentException( 'Unknown backend: ' . $config['backend'] );
		}
	}

	private function getMongoDbCollection( $config ) {
		$connection = new \Doctrine\MongoDB\Connection( $config['server'] );
		if( !$connection->connect() ) {
			throw new RuntimeException( 'Fail to connect to MongoDb' );
		}

		return $connection
			->selectDatabase( $config['database'] )
			->selectCollection( 'entity' );
	}

	private function buildCache( $config ) {
		$caches = array();

		if( $config['array']['enabled'] ) {
			$caches[] = new ArrayCache();
		}

		if( $config['memcached']['enabled'] ) {
			$memcached = new Memcached();

			if( !$memcached->addServer( $config['memcached']['host'], $config['memcached']['port'] ) ) {
				throw new RuntimeException( 'Fail to connect to Memcached' );
			}

			$memcachedCache = new MemcachedCache();
			$memcachedCache->setMemcached($memcached);
			$caches[] = $memcachedCache;
		}

		switch( count( $caches ) ) {
			case 0:
				return null;
			case 1:
				return reset( $caches );
			default:
				return new ChainCache( $caches );
		}
	}

	private function parseConfiguration( $configurationFileName ) {
		$configValues = json_decode( file_get_contents( $configurationFileName ), true );

		$processor = new Processor();
		$configuration = new EntityStoreConfiguration();
		return $processor->processConfiguration(
			$configuration,
			array( $configValues )
		);
	}
}
