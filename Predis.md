## 基本鍵值操作（String）

| 操作 | 方法 | 範例 |
| :--- | :--- | :--- |
| 設定值 | `set($key, $value)` | `$redis->set('name', 'John');` |
| 取得值 | `get($key)` | `$name = $redis->get('name');` |
| 設定並取得舊值 | `getset($key, $value)` | `$old = $redis->getset('count', 10);` |
| 設定（帶過期時間） | `setex($key, $ttl, $value)` | `$redis->setex('lock:123', 300, 'locked');` |
| 只有不存在才設定 | `setnx($key, $value)` | `$redis->setnx('lock', '1');` |
| 同時設定多個 | `mset($array)` | `$redis->mset(['a'=>1, 'b'=>2]);` |
| 同時取得多個 | `mget($keys)` | `$values = $redis->mget(['a', 'b']);` |
| 遞增 +1 | `incr($key)` | `$redis->incr('counter');` |
| 遞增指定數值 | `incrby($key, $amount)` | `$redis->incrby('count', 5);` |
| 遞減 -1 | `decr($key)` | `$redis->decr('counter');` |
| 檢查是否存在 | `exists($key)` | `if ($redis->exists('lock:123')) { ... }` |
| 刪除鍵 | `del($key)` | `$redis->del('temp_key');` |
| 刪除多個 | `del([$key1, $key2])` | `$redis->del(['key1', 'key2']);` |
| 設定過期時間 | `expire($key, $ttl)` | `$redis->expire('session_id', 3600);` |
| 取得剩餘時間 | `ttl($key)` | `$ttl = $redis->ttl('lock:123');` |
| 查看鍵類型 | `type($key)` | `$type = $redis->type('mykey');` |

---

## 有序集合操作（Sorted Set）

| 操作 | 方法 | 說明 |
| :--- | :--- | :--- |
| 加入元素 | `zadd($key, $score, $member)` | 加入有序集合 |
| 加入多個 | `zadd($key, [$score1=>$mem1, $score2=>$mem2])` | 批次加入 |
| 取得排名 | `zrank($key, $member)` | 從低到高排名 (0 = 第 1 名) |
| 取得反向排名 | `zrevrank($key, $member)` | 從高到低排名 |
| 取得範圍內元素 | `zrange($key, $start, $stop)` | 按排名取元素 |
| 取得反向範圍 | `zrevrange($key, $start, $stop)` | 從高到低取 |
| 取得分數區間 | `zrangebyscore($key, $min, $max)` | 按分數區間取 |
| 取得元素數量 | `zcard($key)` | 總元素數 |
| 計算區間數量 | `zcount($key, $min, $max)` | 分數區間內數量 |
| 取得元素分數 | `zscore($key, $member)` | 取得指定元素分數 |
| 增加分數 | `zincrby($key, $increment, $member)` | 分數增量 |
| 移除元素 | `zrem($key, $member)` | 移除指定元素 |
| 按排名移除 | `zremrangebyrank($key, $start, $stop)` | 移除排名範圍 |
| 按分數移除 | `zremrangebyscore($key, $min, $max)` | 移除分數區間 |
| 交集 | `zinterstore($destination, $keys)` | 多個集合交集 |
| 聯集 | `zunionstore($destination, $keys)` | 多個集合聯集 |
