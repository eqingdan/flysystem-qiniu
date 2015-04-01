# Flysystem Adapter for Rackspace.

## Installation

```
composer require eqingdan/flysystem-qiniu
```

## Usage

```
use League\Flysystem\Filesystem;
use EQingdan\Flysystem\Qiniu\QiniuAdapter;

$accessKey = '**********';
$secretKey = '**********';
$bucket = 'sandbox';
$domain = 'sandbox.qiniudn.com';

$filesystem = new Filesystem(new QiniuAdapter($accessKey, $secretKey, $bucket, $domain));
```

## Thanks

- [polev/flysystem-qiniu](https://github.com/polev/flysystem-qiniu)

-EOF-



