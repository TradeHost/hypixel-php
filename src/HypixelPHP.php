<?php
namespace Plancke\HypixelPHP;

use Closure;
use Plancke\HypixelPHP\cache\CacheHandler;
use Plancke\HypixelPHP\cache\CacheTimes;
use Plancke\HypixelPHP\cache\impl\flat\FlatFileCacheHandler;
use Plancke\HypixelPHP\classes\HypixelObject;
use Plancke\HypixelPHP\exceptions\ExceptionCodes;
use Plancke\HypixelPHP\exceptions\HypixelPHPException;
use Plancke\HypixelPHP\fetch\Fetcher;
use Plancke\HypixelPHP\fetch\FetchParams;
use Plancke\HypixelPHP\fetch\FetchTypes;
use Plancke\HypixelPHP\fetch\impl\DefaultFetcher;
use Plancke\HypixelPHP\fetch\Response;
use Plancke\HypixelPHP\log\impl\DefaultLogger;
use Plancke\HypixelPHP\log\Logger;
use Plancke\HypixelPHP\provider\Provider;
use Plancke\HypixelPHP\responses\booster\Boosters;
use Plancke\HypixelPHP\responses\friend\Friends;
use Plancke\HypixelPHP\responses\guild\Guild;
use Plancke\HypixelPHP\responses\KeyInfo;
use Plancke\HypixelPHP\responses\Leaderboards;
use Plancke\HypixelPHP\responses\player\Player;
use Plancke\HypixelPHP\responses\Session;
use Plancke\HypixelPHP\responses\WatchdogStats;
use Plancke\HypixelPHP\util\InputType;
use Plancke\HypixelPHP\util\Utilities;

/**
 * HypixelPHP
 *
 * @author Plancke
 * @version 3.0.0
 * @link https://plancke.io
 *
 */
class HypixelPHP {

    private $apiKey;

    private $options;

    private $loggerGetter;
    private $fetcherGetter;
    private $cacheHandlerGetter;
    private $providerGetter;

    /**
     * @param string $apiKey
     * @param array $options
     * @throws \Exception
     */
    public function __construct($apiKey, $options = []) {
        $this->apiKey = $apiKey;
        $this->options = $options;

        if ($this->apiKey == null) {
            throw new HypixelPHPException("API Key can't be null!", ExceptionCodes::NO_KEY);
        } elseif (InputType::getType($this->apiKey) !== InputType::UUID) {
            throw new HypixelPHPException("API Key is invalid!", ExceptionCodes::INVALID_KEY);
        }

        $this->loggerGetter = function ($HypixelPHP) {
            return new DefaultLogger($HypixelPHP);
        };
        $this->fetcherGetter = function ($HypixelPHP) {
            return new DefaultFetcher($HypixelPHP);
        };
        $this->cacheHandlerGetter = function ($HypixelPHP) {
            return new FlatFileCacheHandler($HypixelPHP);
        };
        $this->providerGetter = function ($HypixelPHP) {
            return new Provider($HypixelPHP);
        };
    }

    /**
     * @return string
     */
    public function getAPIKey() {
        return $this->apiKey;
    }

    /**
     * @param string $apiKey
     * @return $this
     */
    public function setAPIKey($apiKey) {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * @return array
     */
    public function getOptions() {
        return $this->options;
    }

    /**
     * Manually set option array
     *
     * @param $options
     * @return $this
     */
    public function _setOptions($options) {
        $this->options = $options;
        return $this;
    }

    /**
     * @param $input
     * @return $this
     */
    public function setOptions($input) {
        foreach ($input as $key => $val) {
            if ($this->options[$key] != $val) {
                if (is_array($val)) {
                    $this->getLogger()->log('Setting ' . $key . ' to ' . json_encode($val));
                } else {
                    $this->getLogger()->log('Setting ' . $key . ' to ' . $val);
                }
            }
            $this->options[$key] = $val;
        }
        return $this;
    }

    /**
     * @return Logger
     */
    public function getLogger() {
        $getter = $this->loggerGetter;
        return $getter($this);
    }

    /**
     * @param Logger $logger
     * @return $this
     */
    public function setLogger($logger) {
        $this->loggerGetter = function ($HypixelAPI) use ($logger) {
            return $logger;
        };
        return $this;
    }

    public function setLoggerGetter(Closure $getter) {
        $this->loggerGetter = $getter;
        return $this;
    }

    /**
     * @return Fetcher
     */
    public function getFetcher() {
        $getter = $this->fetcherGetter;
        return $getter($this);
    }

    /**
     * @param Fetcher $fetcher
     * @return $this
     */
    public function setFetcher(Fetcher $fetcher) {
        $this->fetcherGetter = function ($HypixelAPI) use ($fetcher) {
            return $fetcher;
        };
        return $this;
    }

    public function setFetcherGetter(Closure $getter) {
        $this->fetcherGetter = $getter;
        return $this;
    }

    /**
     * @return CacheHandler
     */
    public function getCacheHandler() {
        $getter = $this->cacheHandlerGetter;
        return $getter($this);
    }

    /**
     * @param CacheHandler $cacheHandler
     * @return $this
     */
    public function setCacheHandler(CacheHandler $cacheHandler) {
        $this->cacheHandlerGetter = function ($HypixelAPI) use ($cacheHandler) {
            return $cacheHandler;
        };
        return $this;
    }

    public function setCacheHandlerGetter(Closure $getter) {
        $this->cacheHandlerGetter = $getter;
        return $this;
    }

    /**
     * @return Provider
     */
    public function getProvider() {
        $getter = $this->cacheHandlerGetter;
        return $getter($this);
    }

    /**
     * @param Provider $provider
     * @return $this
     */
    public function setProvider(Provider $provider) {
        $this->providerGetter = function ($HypixelAPI) use ($provider) {
            return $provider;
        };
        return $this;
    }

    public function setProviderGetter(Closure $getter) {
        $this->providerGetter = $getter;
        return $this;
    }

    /**
     * @param array $pairs
     * @return Player|Response|null
     */
    public function getPlayer($pairs = []) {
        foreach ($pairs as $key => $val) {
            if ($val == null || $val != '') continue;

            if ($key == FetchParams::PLAYER_BY_UNKNOWN || $key == FetchParams::PLAYER_BY_NAME) {
                return $this->getPlayer([FetchParams::PLAYER_BY_UUID => $this->getUUIDFromVar($val)]);
            }

            if ($key == FetchParams::PLAYER_BY_UUID) {
                if (InputType::getType($val) !== InputType::UUID) continue;
                $val = Utilities::ensureNoDashesUUID($val);

                return $this->handle(
                    $this->getCacheHandler()->getCachedPlayer((string)$val),
                    function () use ($key, $val) {
                        return $this->getFetcher()->fetch(FetchTypes::PLAYER, [$key => $val]);
                    },
                    $this->getProvider()->getPlayer()
                );
            }
        }
        return null;
    }

    public function getUUIDFromVar($value) {
        switch (InputType::getType($value)) {
            case InputType::USERNAME:
                return $this->getUUID((string)$value);
            case InputType::UUID:
                return $value;
            case InputType::PLAYER_OBJECT:
                /** @var Player $value */
                return $value->getUUID();
        }
        return null;
    }

    /**
     * Function to get and cache UUID from username.
     * @param string $username
     *
     * @return string|null
     */
    public function getUUID($username) {
        $username = strtolower((string)$username);
        $cached = $this->getCacheHandler()->getUUID($username);
        if ($cached != null) {
            return $cached;
        }

        if ($this->getCacheHandler()->getCacheTime(CacheTimes::UUID) == CacheHandler::MAX_CACHE_TIME) {
            // we're on max cache, ignore fetching
            // save a null value so we don't spam
            $obj = [
                'timestamp' => time(),
                'name_lowercase' => $username,
                'uuid' => null
            ];
            $this->getCacheHandler()->setPlayerUUID($username, $obj);
            return null;
        }

        {
            // try to use mojang
            $uuidURL = sprintf('https://api.mojang.com/users/profiles/minecraft/%s', $username);
            $response = $this->getFetcher()->getURLContents($uuidURL);
            if (isset($response['id'])) {
                $obj = [
                    'timestamp' => time(),
                    'name_lowercase' => $username,
                    'uuid' => Utilities::ensureNoDashesUUID((string)$response['id'])
                ];
                $this->getCacheHandler()->setPlayerUUID($username, $obj);
                return $obj['uuid'];
            }

            // if all else fails fall back to hypixel
            $response = $this->getFetcher()->fetch(FetchTypes::PLAYER, [FetchParams::PLAYER_BY_NAME => $username]);
            if ($response->wasSuccessful()) {
                $obj = [
                    'timestamp' => time(),
                    'name_lowercase' => $username,
                    'uuid' => Utilities::ensureNoDashesUUID((string)$response->getData()['uuid'])
                ];

                $this->getCacheHandler()->setPlayerUUID($username, $obj);

                return $obj['uuid'];
            }
        }

        if ($this->getCacheHandler()->getCacheTime(CacheTimes::UUID) != CacheHandler::MAX_CACHE_TIME) {
            $this->getCacheHandler()->setCacheTime(CacheTimes::UUID, CacheHandler::MAX_CACHE_TIME);
            return $this->getUUID($username);
        }
        return null;
    }

    /**
     * @param array $pairs
     * @return Guild|Response|null
     */
    public function getGuild($pairs = []) {
        foreach ($pairs as $key => $val) {
            if ($val != null && $val != '') continue;

            if ($key == FetchParams::GUILD_BY_PLAYER_UNKNOWN || $key == FetchParams::GUILD_BY_PLAYER_NAME || $key == FetchParams::GUILD_BY_PLAYER_OBJECT) {
                return $this->getGuild([FetchParams::GUILD_BY_PLAYER_UUID => $this->getUUIDFromVar($val)]);
            }

            if ($key == FetchParams::GUILD_BY_PLAYER_UUID) {
                if (InputType::getType($val) != InputType::UUID) continue;
                $val = Utilities::ensureNoDashesUUID($val);

                $id = $this->getCacheHandler()->getGuildIDForUUID($val);
                if ($id != null) {
                    if ($id instanceof Guild) {
                        return $id;
                    } else {
                        return $this->getGuild([FetchParams::GUILD_BY_ID => $id]);
                    }
                }

                $response = $this->getFetcher()->fetch(FetchTypes::FIND_GUILD, [$key => $val]);
                if ($response->wasSuccessful()) {
                    $content = [
                        'timestamp' => time(),
                        'uuid' => $val,
                        'guild' => $response->getData()['guild']
                    ];

                    $this->getCacheHandler()->setGuildIDForUUID($val, $content);

                    return $this->getGuild([FetchParams::GUILD_BY_ID => $content['guild']]);
                }
            }

            if ($key == FetchParams::GUILD_BY_NAME) {
                $val = strtolower((string)$val);
                $id = $this->getCacheHandler()->getGuildIDForName($val);
                if ($id != null) {
                    if ($id instanceof Guild) {
                        return $id;
                    } else {
                        return $this->getGuild([FetchParams::GUILD_BY_ID => $id]);
                    }
                }

                $response = $this->getFetcher()->fetch(FetchTypes::FIND_GUILD, [$key => $val]);
                if ($response->wasSuccessful()) {
                    $content = [
                        'timestamp' => time(),
                        'name_lower' => $val,
                        'guild' => $response->getData()['guild']
                    ];

                    $this->getCacheHandler()->setGuildIDForName($val, $content);

                    return $this->getGuild([FetchParams::GUILD_BY_ID => $content['guild']]);
                }
            }

            if ($key == FetchParams::GUILD_BY_ID) {
                return $this->handle(
                    $this->getCacheHandler()->getCachedGuild((string)$val),
                    function () use ($key, $val) {
                        return $this->getFetcher()->fetch(FetchTypes::GUILD, [$key => $val]);
                    },
                    $this->getProvider()->getGuild()
                );
            }
        }
        return null;
    }

    /**
     * @param array $pairs
     * @return Session|Response|null
     */
    public function getSession($pairs = []) {
        foreach ($pairs as $key => $val) {
            if ($val != null && $val != '') continue;

            if ($key == FetchParams::SESSION_BY_PLAYER_OBJECT) {
                return $this->getSession([FetchParams::SESSION_BY_UUID => $this->getUUIDFromVar($val)]);
            }

            if ($key == FetchParams::SESSION_BY_UUID) {
                if (InputType::getType($val) != InputType::UUID) continue;
                $val = Utilities::ensureNoDashesUUID($val);

                return $this->handle(
                    $this->getCacheHandler()->getCachedSession((string)$val),
                    function () use ($key, $val) {
                        return $this->getFetcher()->fetch(FetchTypes::SESSION, [$key => $val]);
                    },
                    $this->getProvider()->getSession()
                );
            }
        }
        return null;
    }

    /**
     * @param array $pairs
     * @return Friends|Response|null
     */
    public function getFriends($pairs = []) {
        foreach ($pairs as $key => $val) {
            if ($val != null && $val != '') continue;

            if ($key == FetchParams::FRIENDS_BY_PLAYER_OBJECT) {
                return $this->getFriends([FetchParams::FRIENDS_BY_UUID => $this->getUUIDFromVar($val)]);
            }

            if ($key == FetchParams::FRIENDS_BY_UUID) {
                if (InputType::getType($val) != InputType::UUID) continue;
                $val = Utilities::ensureNoDashesUUID($val);

                return $this->handle(
                    $this->getCacheHandler()->getCachedFriends((string)$val),
                    function () use ($key, $val) {
                        return $this->getFetcher()->fetch(FetchTypes::FRIENDS, [$key => $val]);
                    },
                    $this->getProvider()->getFriends()
                );
            }
        }
        return null;
    }

    /**
     * @return Boosters|Response|null
     */
    public function getBoosters() {
        return $this->handle(
            $this->getCacheHandler()->getCachedBoosters(),
            function () {
                return $this->getFetcher()->fetch(FetchTypes::BOOSTERS);
            },
            $this->getProvider()->getBoosters()
        );
    }

    /**
     * @return Leaderboards|Response|null
     */
    public function getLeaderboards() {
        return $this->handle(
            $this->getCacheHandler()->getCachedLeaderboards(),
            function () {
                return $this->getFetcher()->fetch(FetchTypes::LEADERBOARDS);
            },
            $this->getProvider()->getLeaderboards()
        );
    }

    /**
     * @return KeyInfo|Response|null
     */
    public function getKeyInfo() {
        return $this->handle(
            $this->getCacheHandler()->getCachedKeyInfo($this->getAPIKey()),
            function () {
                return $this->getFetcher()->fetch(FetchTypes::KEY);
            },
            $this->getProvider()->getKeyInfo()
        );
    }

    /**
     * @return WatchdogStats|Response|null
     */
    public function getWatchdogStats() {
        return $this->handle(
            $this->getCacheHandler()->getCachedWatchdogStats(),
            function () {
                return $this->getFetcher()->fetch(FetchTypes::WATCHDOG_STATS);
            },
            $this->getProvider()->getWatchdogStats()
        );
    }

    /**
     * Handles cache expiry checks,
     * fetching new objects if needed and
     * loads cached extra data if applicable
     *
     * @param $responseSupplier
     * @param $constructor
     * @param HypixelObject $cached
     *
     * @return HypixelObject|Response|null
     */
    private function handle(HypixelObject $cached, $responseSupplier, $constructor) {
        if ($cached instanceof HypixelObject && !$cached->isCacheExpired()) {
            return $cached;
        }

        $response = $responseSupplier();
        if ($response instanceof Response) {
            if ($response->wasSuccessful()) {
                $fetched = $constructor($this, $response->getData());
                if ($fetched instanceof HypixelObject) {
                    if ($cached instanceof HypixelObject) {
                        $fetched->_setExtra($cached->getExtra());
                    }

                    return $this->getCacheHandler()->_setCache($fetched->handleNew());
                }
            } else {
                // fetch was not successful, attach response or
                // return it so we can get the error
                if ($cached != null) {
                    $cached->attachResponse($response);
                } else {
                    return $response;
                }
            }
        }

        return $cached;
    }

    /**
     * @param $in
     * @return HypixelObject|null
     */
    public function ignoreResponse($in) {
        if ($in instanceof HypixelObject) {
            return $in;
        }
        return null;
    }

    /**
     * @param $in
     * @return Response|null
     */
    public function getResponse($in) {
        if ($in instanceof HypixelObject) {
            return $in->getResponse();
        } else if ($in instanceof Response) {
            return $in;
        }
        return null;
    }
}