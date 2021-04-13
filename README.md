# yii2-minio
Yii2 MinIO

## Installation

```shell
php composer.phar require bevin1984/yii2-minio:^0.0.1
```
Or

```php
"bevin1984/yii2-minio": "^0.0.1"
```

## Configuration

```php
'components' => [
    'minio' => [
        'class'=> 'bevin1984\MinioClient',
        'key' => '<your key>',
        'secret' => '<your secret>',
        'endpoint'=> '<your endpoint>',
        'region' => '<your region>',
        'bucket' => '<your bucket>'
    ],
]
```

## Usage

### Writing files

To write file
```php
Yii::$app->minio->write('filename.ext', 'contents');
```

To write file using stream contents
```php
$stream = fopen('/path/to/somefile.ext', 'r+');
Yii::$app->minio->writeStream('filename.ext', $stream);
```

### Reading files

To read file
```php
$contents = Yii::$app->minio->read('filename.ext');
```

To retrieve a read-stream
```php
$stream = Yii::$app->minio->readStream('filename.ext');
$contents = stream_get_contents($stream);
fclose($stream);
```

### Saving file
To save file
```php
Yii::$app->minio->save('filename.ext', '/path/to/somefile.ext');
```

### Checking if a file exists

To check if a file exists
```php
$exists = Yii::$app->minio->has('filename.ext');
```

### Deleting files

To delete file
```php
Yii::$app->minio->delete('filename.ext');
```

### Copying files

To copy file

```php
Yii::$app->minio->copy('filename.ext', 'newname.ext');
```

### Renaming files

To rename file

```php
Yii::$app->minio->rename('filename.ext', 'newname.ext');
```

### Getting files mimetype

To get file mimetype

```php
$mimetype = Yii::$app->minio->getMimetype('filename.ext');
```

### Getting files timestamp

To get file timestamp

```php
$timestamp = Yii::$app->minio->getTimestamp('filename.ext');
```

### Getting files size

To get file size

```php
$size = Yii::$app->minio->getSize('filename.ext');
```

### Getting url

To get file url

```php
$url = Yii::$app->minio->getObjectUrl('filename.ext');
```

To get file presigned url

```php
$url = Yii::$app->minio->getPresignedUrl('filename.ext', 300);
```