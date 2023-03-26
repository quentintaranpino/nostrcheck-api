<?php

/**
 * TokenBucket is an implementation of the token bucket algorithm covered here:
 * https://en.wikipedia.org/wiki/Token_bucket
 *
 * Instances of the class have a persistent storage, an identifier for the bucket, a
 * maximum capacity of that bucket, and a fill rate defined as the number of tokens
 * per second that get added to the bucket.
 * Code from: https://ryanbritton.com/2016/11/rate-limiting-in-php-with-token-bucket/
 */
class TokenBucket
{
    /* Constants to map from tokens per unit to tokens per second */
    const MILLISECOND = 0.001;
    const SECOND = 1;
    const MINUTE = 60;
    const HOUR = 3600;
    const DAY = 86400;
    const WEEK = 604800;
     
    const MICROTIME_DELTA = 0.0001; // microtime(true) has four significant digits to the 
                                    // right of the decimal
     
    private $_storage;
    protected $_identifier;
    protected $_bucket_capacity;
    protected $_tokens_per_second;
     
    /**
     * Compares the two microtime values in a float-compatible way and returns the result.
     *
     * @param float $m1 The first microtime.
     * @param float $m2 The second microtime.
     * @return 0 if equal, 1 if $m1 > $m2, and -1 if $m1 < $m2.
     */
    public static function compare_microtimes($m1, $m2)
    {
        if (($m2 == 0 && ($m1 == 0 || abs($m1 - $m2) < static::MICROTIME_DELTA)) ||
             abs(($m1 - $m2) / $m2) < static::MICROTIME_DELTA)
        {
            return 0;
        }
         
        if ($m1 > $m2) { return 1; }
        return -1;
    }
     
    /**
     * TokenBucket constructor.
     *
     * @param TokenBucketStorage $storage The persistent storage to use.
     * @param string $identifier The identifier for the bucket record in the storage.
     * @param int $bucket_capacity Maximum capacity of the bucket.
     * @param double $tokens_per_second Number of tokens per second added to the bucket.
     */
    public function __construct(TokenBucketStorage $storage, $identifier, $bucket_capacity, $tokens_per_second)
    {
        assert($bucket_capacity > 0, "Token Bucket capacity must be > 0");
     
        $this->_storage = $storage;
        $this->_identifier = $identifier;
        $this->_bucket_capacity = $bucket_capacity;
        $this->_tokens_per_second = $tokens_per_second;
    }
     
    /**
     * Atomically checks the available token count, creating the initial record if needed, 
     * and updates the available token count if the requested number of tokens is 
     * available.
     *
     * @param int $token_count The number of tokens to consume.
     * @return bool Whether or not there were enough tokens to satisfy the request.
     */
    public function consume($token_count = 1)
    {
        if ($token_count > $this->_bucket_capacity)
        {
            return false;
        }
         
        $microtime = $this->_tokens_to_seconds($token_count);
        $full_microtime = $this->_tokens_to_seconds($this->_bucket_capacity);
        return $this->_storage->consume($this->_identifier, $microtime, $full_microtime, function() use ($token_count) {
            $this->_bootstrap($this->_bucket_capacity - $token_count);
            return true;
        });
    }
     
    /**
     * Creates an initial record with the given number of tokens.
     *
     * @param int $initialTokens
     */
    protected function _bootstrap($initialTokens)
    {
        $microtime = microtime(true) - $this->_tokens_to_seconds($initialTokens);
        $this->_storage->bootstrap($this->_identifier, $microtime);
    }
     
    protected function _tokens_to_seconds($tokens)
    {
        return $tokens / $this->_tokens_per_second;
    }
     
    protected function _seconds_to_tokens($seconds)
    {
        return (int) $seconds * $this->_tokens_per_second;
    }
}
 
/**
 * TokenBucketStorage defines the interface that TokenBucket will use to interact with the 
 * backing storage. Rather than using token counts, it uses time deltas so the storage 
 * does not need to be aware of the refill rate of the bucket.
 */
interface TokenBucketStorage
{
    /**
     * Prepares the underlying storage for use (e.g., creates the database table). This is
     * intended to be called by the application code as a convenience and will not be 
     * called by anything in TokenBucket.
     */
    public function prepare();
     
    /**
     * Creates an initial record with the given identifier and microtime. May be called
     * from within consume().
     *
     * @param string $identifier The bucket identifier.
     * @param double $microtime The microtime that defines the initial token count.
     */
    public function bootstrap($identifier, $microtime);
     
    /**
     * Attempts to consume the microtime equivalent of n tokens for the given bucket 
     * identifier. Implementations are expected to enforce capacity bounds checking and
     * ensure consumption is an atomic operation.
     *
     * @param string $identifier The bucket identifier.
     * @param double $microtime The microtime equivalent of the number of tokens to 
     *                          consume.
     * @param double $full_microtime The microtime equivalent of a full bucket.
     * @param closure $bootstrap_closure Closure to call if the bucket does not exist.
     * @return bool Whether or not there were enough tokens to satisfy the request.
     */
    public function consume($identifier, $microtime, $full_microtime, $bootstrap_closure);
}
 
/**
 * Sample implementation of TokenBucketStorage that uses MySQL through a PDO handle.
 */
class TokenBucketStoragePDOMySQL implements TokenBucketStorage
{
    private $_db;
    private $_table_name;
     
    /**
     * TokenBucketStoragePDOMySQL constructor.
     *
     * @param PDO $db A PDO handle for the database.
     */
    public function __construct($db, $table_name)
    {
        $this->_db = $db;
        $this->_table_name = $table_name;
    }
     
    public function prepare()
    {
        $query_string = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->_table_name}` (
  `identifier` varchar(255) NOT NULL,
  `microtime` double NOT NULL,
  PRIMARY KEY (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SQL;
        $query = $this->_db->query($query_string);
    }
     
    public function bootstrap($identifier, $microtime)
    {
        $query = $this->_db->prepare("INSERT INTO {$this->_table_name} (identifier, microtime) VALUES (:id, :time)");
        $query->execute([
            ':id' => $identifier,
            ':time' => $microtime,
        ]);
    }
     
    public function consume($identifier, $microtime, $full_microtime, $bootstrap_closure)
    {
        // We need to ensure the operation is atomic, so we begin an explicit transaction
        // and lock the record.
        $this->_db->beginTransaction();
        $query = $this->_db->prepare("SELECT * FROM {$this->_table_name} WHERE identifier = :id FOR UPDATE");
        $query->execute([
            ':id' => $identifier,
        ]);
        $record = $query->fetch(PDO::FETCH_ASSOC);
        if (!is_array($record))
        {
            // Bucket does not exist, run the provided bootstrap closure
            if ($bootstrap_closure())
            {
                $this->_db->commit();
                return true;
            }
             
            $this->_db->rollBack();
            return false;
        }
         
        // Check for availability, capping it to the capacity of the bucket
        $now = microtime(true);
        $available = min($now - $record['microtime'], $full_microtime);
        if (TokenBucket::compare_microtimes($microtime, $available) > 0)
        {
            $this->_db->rollBack();
            return false;
        }
         
        // Consume the tokens, purging any that are overfilled
        $query = $this->_db->prepare("UPDATE {$this->_table_name} SET microtime = :time WHERE identifier = :id");
        $query->execute([
            ':id' => $identifier,
            ':time' => $now - $available + $microtime,
        ]);
        $this->_db->commit();
        return true;
    }
}

?>