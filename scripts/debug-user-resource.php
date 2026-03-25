<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$site = require '/home/donk/development/flarum-site/site.php';
$app = $site->bootApp();
$container = $app->getContainer();

echo "Enabled extensions:\n";

$extensionManager = $container->make(\Flarum\Extension\ExtensionManager::class);

foreach ($extensionManager->getEnabledExtensions() as $extension) {
    echo ' - '.$extension->getId()."\n";
}

echo "\nUserResource field modifiers:\n";

$reflection = new ReflectionClass(\Flarum\Api\Resource\UserResource::class);
$property = $reflection->getProperty('fieldModifiers');
$property->setAccessible(true);
$modifiers = $property->getValue();

var_export(array_keys($modifiers));
echo "\n";
var_export(isset($modifiers[\Flarum\Api\Resource\UserResource::class]));
echo "\n";

echo "\nResolved field names:\n";

$resource = $container->make(\Flarum\Api\Resource\UserResource::class);

foreach ($resource->resolveFields() as $field) {
    echo ' - '.$field->name."\n";
}
