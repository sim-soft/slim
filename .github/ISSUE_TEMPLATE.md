Hello,

I encountered an issue with the following code:

```php
$recordSet =  Query::db('mysql')
            ->select('attr1', 'attr2')
            ->from('table1')
            ->where('attr1', '>', 10)
            ->getArray();

foreach ($recordSet as $data){
 echo $data['attr1'];
}
```

APP version: PUT HERE YOUR APP VERSION (exact version)

PHP version: PUT HERE YOUR PHP VERSION

I expected to get:

```php
wct200514135314e7x4d
```

But I actually get:

```php
null
```

Thanks!
