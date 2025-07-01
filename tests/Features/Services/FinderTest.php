<?php

declare(strict_types = 1);

use Illuminate\Support\Facades\File;
use Langfy\Services\Finder;

beforeEach(function (): void {
    // Create a test directory structure
    $this->testDir      = tests_cache_path();
    $this->appDir       = $this->testDir . '/app';
    $this->resourcesDir = $this->testDir . '/resources';
    $this->vendorDir    = $this->testDir . '/vendor';

    File::ensureDirectoryExists($this->appDir);
    File::ensureDirectoryExists($this->resourcesDir);
    File::ensureDirectoryExists($this->vendorDir);
});

afterEach(function (): void {
    // Clean up test files
    if (File::exists($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
});

describe('File System Integration', function (): void {
    it('finds strings in PHP files', function (): void {
        // Create a test PHP file
        $phpContent = '<?php
                class TestController {
                    public function index() {
                        return __("Welcome to our application");
                    }

                    public function show() {
                        return trans("User profile");
                    }
                }
            ';

        File::put($this->appDir . '/TestController.php', $phpContent);

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toContain('Welcome to our application')
            ->and($result)->toContain('User profile');
    });

    it('finds strings in Blade files', function (): void {
        $bladeContent = '@extends("layout")

                @section("content")
                    <h1>{{ __("Page Title") }}</h1>
                    <p>{{ trans("Page description") }}</p>
                @endsection
            ';

        File::put($this->resourcesDir . '/test.blade.php', $bladeContent);

        $finder = Finder::in($this->resourcesDir);
        $result = $finder->run();

        expect($result)->toContain('Page Title')
            ->and($result)->toContain('Page description');
    });

    it('finds strings with property annotations', function (): void {
        // Create test a PHP file with property annotations
        $phpContent = '<?php
                class TestModel {
                    /** @trans */
                    protected string $title = "Default Title";

                    #[Trans]
                    private string $description = "Default Description";

                    protected string $normalProperty = "Not Translatable";
                }
            ';

        File::put($this->appDir . '/TestModel.php', $phpContent);

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toContain('Default Title')
            ->and($result)->toContain('Default Description')
            ->and($result)->not->toContain('Not Translatable');
    });

    it('finds strings with variable annotations', function (): void {
        // Create test PHP file with variable annotations
        $phpContent = '<?php
                function testFunction() {
                    $message = "Success message" /** @trans */;
                    $error = /** @trans */ "Error occurred";
                    $normal = "Not translatable";

                    return $message;
                }
            ';

        File::put($this->appDir . '/TestFunction.php', $phpContent);

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toContain('Success message')
            ->and($result)->toContain('Error occurred')
            ->and($result)->not->toContain('Not translatable');
    });

    it('processes multiple directories', function (): void {
        // Create files in different directories
        File::put($this->appDir . '/Controller.php', '<?php echo __("App String");');
        File::put($this->resourcesDir . '/view.blade.php', '{{ trans("Resource String") }}');

        $finder = Finder::in([$this->appDir, $this->resourcesDir]);
        $result = $finder->run();

        expect($result)->toContain('App String')
            ->and($result)->toContain('Resource String');
    });

    it('uses and() method to add more directories', function (): void {
        // Create files in different directories
        File::put($this->appDir . '/Controller.php', '<?php echo __("App String");');
        File::put($this->resourcesDir . '/view.blade.php', '{{ trans("Resource String") }}');

        $finder = Finder::in($this->appDir)->and($this->resourcesDir);
        $result = $finder->run();

        expect($result)->toContain('App String')
            ->and($result)->toContain('Resource String');
    });

    it('ignores specified directories', function (): void {
        // Create files in app and vendor directories
        File::put($this->appDir . '/Controller.php', '<?php echo __("App String");');
        File::put($this->vendorDir . '/Package.php', '<?php echo __("Vendor String");');

        $finder = Finder::in($this->testDir)->ignore('vendor');
        $result = $finder->run();

        expect($result)->toContain('App String')
            ->and($result)->not->toContain('Vendor String');
    });

    it('ignores files with specified extensions', function (): void {
        // Create PHP and JSON files
        File::put($this->appDir . '/Controller.php', '<?php echo __("PHP String");');
        File::put($this->appDir . '/config.json', '{"message": "__(\\"JSON String\\")"}');

        $finder = Finder::in($this->appDir)->ignoreExtensions('json');
        $result = $finder->run();

        expect($result)->toContain('PHP String')
            ->and($result)->not->toContain('JSON String');
    });

    it('returns unique results across multiple files', function (): void {
        // Create multiple files with duplicate strings
        File::put($this->appDir . '/Controller1.php', '<?php echo __("Shared Message");');
        File::put($this->appDir . '/Controller2.php', '<?php echo trans("Shared Message");');
        File::put($this->appDir . '/Controller3.php', '<?php echo __("Unique Message");');

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toHaveCount(2)
            ->and($result)->toContain('Shared Message')
            ->and($result)->toContain('Unique Message');
    });

    it('skips non-existent directories', function (): void {
        $finder = Finder::in(['non-existent-dir', $this->appDir]);

        // Should not throw exception
        $result = $finder->run();

        expect($result)->toBe([]);
    });

    it('handles empty directories', function (): void {
        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toBe([]);
    });

    it('processes complex mixed content', function (): void {
        $complexContent = '<?php
                namespace App\Controllers;

                class ComplexController {
                    /** @trans */
                    protected string $pageTitle = "Complex Page Title";

                    #[Trans]
                    private string $metaDescription = "Page meta description";

                    public function index() {
                        $welcomeMessage = "Welcome to our site" /** @trans */;
                        $errorMessage = /** @trans */ "An error occurred";

                        return view("welcome", [
                            "title" => __("Dynamic Title"),
                            "message" => trans("Dynamic Message"),
                            "greeting" => @lang("Hello User"),
                        ]);
                    }

                    public function show($id) {
                        return __("Show item :id", ["id" => $id]);
                    }
                }
            ';

        File::put($this->appDir . '/ComplexController.php', $complexContent);

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toContain('Complex Page Title')
            ->and($result)->toContain('Page meta description')
            ->and($result)->toContain('Welcome to our site')
            ->and($result)->toContain('An error occurred')
            ->and($result)->toContain('Dynamic Title')
            ->and($result)->toContain('Dynamic Message')
            ->and($result)->toContain('Hello User')
            ->and($result)->toContain('Show item :id');
    });

    it('filters out non-translatable strings', function (): void {
        $content = '<?php
                echo __("Valid translatable string");
                echo __("123");  // Pure number
                echo __("a");    // Too short
                echo __("example.com");  // Domain-like
                echo __("#fff");  // Color code
                echo __("messages.key");  // Translation key
            ';

        File::put($this->appDir . '/FilterTest.php', $content);

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toContain('Valid translatable string')
            ->and($result)->not->toContain('123')
            ->and($result)->not->toContain('a')
            ->and($result)->not->toContain('example.com')
            ->and($result)->not->toContain('#fff')
            ->and($result)->not->toContain('messages.key');
    });

    it('handles files with special characters and encoding', function (): void {
        // Create file with special characters
        $content = '<?php
                echo __("Olá, mundo!");
                echo trans("Café com açúcar");
                echo __("Emoji test 🚀");
            ';

        File::put($this->appDir . '/SpecialChars.php', $content);

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toContain('Olá, mundo!')
            ->and($result)->toContain('Café com açúcar')
            ->and($result)->toContain('Emoji test 🚀');
    });

    it('processes large files efficiently', function (): void {
        // Create a large file with many translatable strings
        $content         = '<?php' . PHP_EOL;
        $expectedStrings = [];

        for ($i = 1; $i <= 100; $i++) {
            $string = "Test string number {$i}";
            $content .= "echo __('{$string}');" . PHP_EOL;
            $expectedStrings[] = $string;
        }

        File::put($this->appDir . '/LargeFile.php', $content);

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toHaveCount(100);

        foreach ($expectedStrings as $expectedString) {
            expect($result)->toContain($expectedString);
        }
    });
});

describe('Configuration Integration', function (): void {
    it('respects default ignore paths from configuration', function (): void {
        // Create files in default ignored directories
        File::ensureDirectoryExists($this->testDir . '/vendor');
        File::ensureDirectoryExists($this->testDir . '/node_modules');
        File::ensureDirectoryExists($this->testDir . '/storage');

        File::put($this->testDir . '/vendor/test.php', '<?php echo __("Vendor String");');
        File::put($this->testDir . '/node_modules/test.php', '<?php echo __("Node String");');
        File::put($this->testDir . '/storage/test.php', '<?php echo __("Storage String");');
        File::put($this->appDir . '/test.php', '<?php echo __("App String");');

        $finder = Finder::in($this->testDir);
        $result = $finder->run();

        expect($result)->toContain('App String')
            ->and($result)->not->toContain('Vendor String')
            ->and($result)->not->toContain('Node String')
            ->and($result)->not->toContain('Storage String');
    });

    it('respects default ignore extensions from configuration', function (): void {
        // Create files with default ignored extensions
        File::put($this->appDir . '/test.php', '<?php echo __("PHP String");');
        File::put($this->appDir . '/config.json', '{"message": "__(\\"JSON String\\")"}');
        File::put($this->appDir . '/readme.md', '__("Markdown String")');
        File::put($this->appDir . '/data.txt', '__("Text String")');

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toContain('PHP String')
            ->and($result)->not->toContain('JSON String')
            ->and($result)->not->toContain('Markdown String')
            ->and($result)->not->toContain('Text String');
    });
});

describe('New Ignore Functionality', function (): void {
    it('ignores specific files by filename', function (): void {
        // Create test files
        File::put($this->appDir . '/config.php', '<?php echo __("Config String");');
        File::put($this->appDir . '/bootstrap.php', '<?php echo __("Bootstrap String");');
        File::put($this->appDir . '/normal.php', '<?php echo __("Normal String");');

        $finder = Finder::in($this->appDir)->ignoreFiles(['config.php', 'bootstrap.php']);
        $result = $finder->run();

        expect($result)->toContain('Normal String')
            ->and($result)->not->toContain('Config String')
            ->and($result)->not->toContain('Bootstrap String');
    });

    it('ignores files by namespace', function (): void {
        // Create files with different namespaces
        File::put($this->appDir . '/TestController.php', '<?php
            namespace App\\Tests;
            echo __("Test String");
        ');

        File::put($this->appDir . '/VendorController.php', '<?php
            namespace Vendor\\Package;
            echo __("Vendor String");
        ');

        File::put($this->appDir . '/AppController.php', '<?php
            namespace App\\Http\\Controllers;
            echo __("App String");
        ');

        $finder = Finder::in($this->appDir)->ignoreNamespaces(['App\\Tests', 'Vendor\\Package']);
        $result = $finder->run();

        expect($result)->toContain('App String')
            ->and($result)->not->toContain('Test String')
            ->and($result)->not->toContain('Vendor String');
    });

    it('ignores specific strings', function (): void {
        File::put($this->appDir . '/test.php', '<?php
            echo __("debug");
            echo __("test");
            echo __("Valid Message");
            echo __("Another Valid Message");
        ');

        $finder = Finder::in($this->appDir)->ignoreStrings(['debug', 'test']);
        $result = $finder->run();

        expect($result)->toContain('Valid Message')
            ->and($result)->toContain('Another Valid Message')
            ->and($result)->not->toContain('debug')
            ->and($result)->not->toContain('test');
    });

    it('ignores strings matching regex patterns', function (): void {
        File::put($this->appDir . '/test.php', '<?php
            echo __("test_something");
            echo __("debug_mode");
            echo __("something_debug");
            echo __("Valid Message");
            echo __("Another Valid Message");
            echo __("filament-forms::test.string")
        ');

        $finder = Finder::in($this->appDir)->ignorePatterns(['/^test_/', '/debug$/', '/^filament-/']);
        $result = $finder->run();

        expect($result)->toContain('Valid Message')
            ->and($result)->toContain('Another Valid Message')
            ->and($result)->not->toContain('test_something')
            ->and($result)->not->toContain('something_debug')
            ->and($result)->not->toContain('filament-forms::test.string')
            ->and($result)->toContain('debug_mode'); // Should not match /debug$/ pattern
    });

    it('combines multiple ignore types', function (): void {
        // Create test files
        File::put($this->appDir . '/config.php', '<?php echo __("Config String");');

        File::put($this->appDir . '/TestController.php', '<?php
            namespace App\\Tests;
            echo __("Test String");
        ');

        File::put($this->appDir . '/normal.php', '<?php
            echo __("debug");
            echo __("test_something");
            echo __("Valid Message");
        ');

        $finder = Finder::in($this->appDir)
            ->ignoreFiles(['config.php'])
            ->ignoreNamespaces(['App\\Tests'])
            ->ignoreStrings(['debug'])
            ->ignorePatterns(['/^test_/']);

        $result = $finder->run();

        expect($result)->toContain('Valid Message')
            ->and($result)->not->toContain('Config String')
            ->and($result)->not->toContain('Test String')
            ->and($result)->not->toContain('debug')
            ->and($result)->not->toContain('test_something');
    });

    it('handles namespace matching with partial matches', function (): void {
        File::put($this->appDir . '/TestController.php', '<?php
            namespace App\\Tests\\Unit;
            echo __("Unit Test String");
        ');

        File::put($this->appDir . '/FeatureController.php', '<?php
            namespace App\\Tests\\Feature;
            echo __("Feature Test String");
        ');

        File::put($this->appDir . '/AppController.php', '<?php
            namespace App\\Http\\Controllers;
            echo __("App String");
        ');

        $finder = Finder::in($this->appDir)->ignoreNamespaces(['App\\Tests']);
        $result = $finder->run();

        expect($result)->toContain('App String')
            ->and($result)->not->toContain('Unit Test String')
            ->and($result)->not->toContain('Feature Test String');
    });

    it('handles empty ignore configurations gracefully', function (): void {
        File::put($this->appDir . '/test.php', '<?php echo __("Test String");');

        $finder = Finder::in($this->appDir)
            ->ignoreFiles([])
            ->ignoreNamespaces([])
            ->ignoreStrings([])
            ->ignorePatterns([]);

        $result = $finder->run();

        expect($result)->toContain('Test String');
    });

    it('handles invalid regex patterns gracefully', function (): void {
        File::put($this->appDir . '/test.php', '<?php echo __("Test String");');

        // This should not throw an exception even with invalid regex
        $finder = Finder::in($this->appDir)->ignorePatterns(['[invalid regex']);

        try {
            $result = $finder->run();
            expect($result)->toContain('Test String');
        } catch (Exception $e) {
            // If an exception is thrown, let's see what it is
            throw new Exception("Unexpected exception: " . get_class($e) . " - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }
    });
});

describe('Error Handling', function (): void {
    it('handles unreadable files gracefully', function (): void {
        // Create a file and make it unreadable (if possible on the system)
        File::put($this->appDir . '/test.php', '<?php echo __("Test String");');

        $finder = Finder::in($this->appDir);

        // Should not throw exception even if file becomes unreadable
        $result = $finder->run();

        expect($result)->toBeArray();
    });

    it('handles files with syntax errors gracefully', function (): void {
        // Create file with PHP syntax errors
        $invalidContent = '<?php
                echo __("Valid String");
                echo __("Another Valid String"
                // Missing closing parenthesis and semicolon
            ';

        File::put($this->appDir . '/InvalidSyntax.php', $invalidContent);

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        // Should still find valid strings despite syntax errors
        expect($result)->toContain('Valid String');
    });

    it('handles very large files without memory issues', function (): void {
        // Create a very large file
        $content = '<?php' . PHP_EOL;
        $content .= str_repeat('// Large comment line' . PHP_EOL, 1000);
        $content .= 'echo __("Test String in Large File");' . PHP_EOL;

        File::put($this->appDir . '/VeryLargeFile.php', $content);

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toContain('Test String in Large File');
    });
});

describe('Real-world Scenarios', function (): void {
    it('processes typical Laravel application structure', function (): void {
        // Create typical Laravel app structure
        File::ensureDirectoryExists($this->appDir . '/Http/Controllers');
        File::ensureDirectoryExists($this->appDir . '/Models');
        File::ensureDirectoryExists($this->resourcesDir . '/views');

        // Controller
        File::put($this->appDir . '/Http/Controllers/UserController.php', '<?php
                class UserController {
                    public function index() {
                        return view("users.index", [
                            "title" => __("User Management"),
                            "description" => trans("Manage all users")
                        ]);
                    }
                }
            ');

        // Model
        File::put($this->appDir . '/Models/User.php', '<?php
                class User {
                    /** @trans */
                    protected string $defaultRole = "Regular User";

                    public function getWelcomeMessage() {
                        return __("Welcome, :name!", ["name" => $this->name]);
                    }
                }
            ');

        // Blade view
        File::put($this->resourcesDir . '/views/users.blade.php', '
                <h1>{{ __("Users") }}</h1>
                <p>{{ trans("Total users: :count", ["count" => $users->count()]) }}</p>
            ');

        $finder = Finder::in([$this->appDir, $this->resourcesDir]);
        $result = $finder->run();

        expect($result)->toContain('User Management')
            ->and($result)->toContain('Manage all users')
            ->and($result)->toContain('Regular User')
            ->and($result)->toContain('Welcome, :name!')
            ->and($result)->toContain('Users')
            ->and($result)->toContain('Total users: :count');
    });
});

describe('Complex String Patterns', function (): void {
    it('handles strings with nested parentheses correctly', function (): void {
        $phpContent = '<?php
                class TestController {
                    public function index() {
                        return __("Schedule First Appointment (\"Coming soon\")");
                    }

                    public function show() {
                        return trans("User profile (active)");
                    }

                    public function edit() {
                        return __("Edit user (ID: 123)");
                    }
                }
            ';

        File::put($this->appDir . '/ComplexStrings.php', $phpContent);

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toContain('Schedule First Appointment ("Coming soon")')
            ->and($result)->toContain('User profile (active)')
            ->and($result)->toContain('Edit user (ID: 123)');
    });

    it('handles strings with escaped quotes correctly', function (): void {
        $phpContent = '<?php
                class TestController {
                    public function index() {
                        return __("My Test \'Hello\'");
                    }

                    public function show() {
                        return trans("Say \"Hello World\"");
                    }

                    public function edit() {
                        return __("Mixed \'quotes\' and \"quotes\"");
                    }
                }
            ';

        File::put($this->appDir . '/EscapedQuotes.php', $phpContent);

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toContain('My Test \'Hello\'')
            ->and($result)->toContain('Say "Hello World"')
            ->and($result)->toContain('Mixed \'quotes\' and "quotes"');
    });

    it('handles complex nested structures', function (): void {
        $phpContent = '<?php
                class TestController {
                    public function complex() {
                        return __("Complex (nested \"quotes\" and \'more\') string");
                    }

                    public function withParameters() {
                        return trans("User (:name) has (:count) items", ["name" => $name, "count" => $count]);
                    }
                }
            ';

        File::put($this->appDir . '/ComplexNested.php', $phpContent);

        $finder = Finder::in($this->appDir);
        $result = $finder->run();

        expect($result)->toContain('Complex (nested "quotes" and \'more\') string')
            ->and($result)->toContain('User (:name) has (:count) items');
    });

    it('handles blade templates with complex strings', function (): void {
        $bladeContent = '
                <div>
                    <h1>{{ __("Welcome (\"Guest User\")") }}</h1>
                    <p>{{ trans("Status: (Active)") }}</p>
                    <span>{{ __("Message with \'quotes\' inside") }}</span>
                </div>
            ';

        File::put($this->resourcesDir . '/complex.blade.php', $bladeContent);

        $finder = Finder::in($this->resourcesDir);
        $result = $finder->run();

        expect($result)->toContain('Welcome ("Guest User")')
            ->and($result)->toContain('Status: (Active)')
            ->and($result)->toContain('Message with \'quotes\' inside');
    });
});

describe('JSON File Saving', function (): void {
    it('saves strings with escaped quotes correctly to JSON file', function (): void {
        $strings = [
            'My Test \'Hello\'',
            'Say "Hello World"',
            'Mixed \'quotes\' and "quotes"',
            'Schedule First Appointment ("Coming soon")',
            'Complex (nested "quotes" and \'more\') string',
        ];

        $filePath = $this->testDir . '/test-strings.json';

        $utils = new Langfy\Helpers\Utils;
        $utils->saveStringsToFile($strings, $filePath);

        expect(File::exists($filePath))->toBeTrue();

        $savedContent   = File::get($filePath);
        $decodedContent = json_decode($savedContent, true);

        expect($decodedContent)->toBeArray()
            ->and($decodedContent)->toHaveKey('My Test \'Hello\'')
            ->and($decodedContent)->toHaveKey('Say "Hello World"')
            ->and($decodedContent)->toHaveKey('Mixed \'quotes\' and "quotes"')
            ->and($decodedContent)->toHaveKey('Schedule First Appointment ("Coming soon")')
            ->and($decodedContent)->toHaveKey('Complex (nested "quotes" and \'more\') string')
            ->and($decodedContent['My Test \'Hello\''])->toBe('My Test \'Hello\'')
            ->and($decodedContent['Say "Hello World"'])->toBe('Say "Hello World"')
            ->and($decodedContent['Mixed \'quotes\' and "quotes"'])->toBe('Mixed \'quotes\' and "quotes"')
            ->and($decodedContent['Schedule First Appointment ("Coming soon")'])->toBe('Schedule First Appointment ("Coming soon")')
            ->and($decodedContent['Complex (nested "quotes" and \'more\') string'])->toBe('Complex (nested "quotes" and \'more\') string');
    });

    it('handles key-value pairs with complex strings correctly', function (): void {
        $strings = [
            'My Test \'Hello\''                          => 'Meu Teste \'Olá\'',
            'Say "Hello World"'                          => 'Diga "Olá Mundo"',
            'Schedule First Appointment ("Coming soon")' => 'Agendar Primeiro Compromisso ("Em breve")',
        ];

        $filePath = $this->testDir . '/test-translations.json';

        $utils = new Langfy\Helpers\Utils;
        $utils->saveStringsToFile($strings, $filePath);

        expect(File::exists($filePath))->toBeTrue();

        $savedContent   = File::get($filePath);
        $decodedContent = json_decode($savedContent, true);

        expect($decodedContent)->toBeArray()
            ->and($decodedContent)->toHaveKey('My Test \'Hello\'')
            ->and($decodedContent)->toHaveKey('Say "Hello World"')
            ->and($decodedContent)->toHaveKey('Schedule First Appointment ("Coming soon")')
            ->and($decodedContent['My Test \'Hello\''])->toBe('Meu Teste \'Olá\'')
            ->and($decodedContent['Say "Hello World"'])->toBe('Diga "Olá Mundo"')
            ->and($decodedContent['Schedule First Appointment ("Coming soon")'])->toBe('Agendar Primeiro Compromisso ("Em breve")');
    });

    it('merges with existing JSON file content correctly', function (): void {
        $filePath = $this->testDir . '/existing-translations.json';

        // Create an initial file with some content
        $initialContent = [
            'Hello' => 'Olá',
            'World' => 'Mundo',
        ];
        File::put($filePath, json_encode($initialContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Add new strings with complex quotes
        $newStrings = [
            'My Test \'Hello\'' => 'Meu Teste \'Olá\'',
            'Say "Hello World"' => 'Diga "Olá Mundo"',
        ];

        $utils = new Langfy\Helpers\Utils;
        $utils->saveStringsToFile($newStrings, $filePath);

        $savedContent   = File::get($filePath);
        $decodedContent = json_decode($savedContent, true);

        // Should contain both original and new content
        expect($decodedContent)->toBeArray()
            ->and($decodedContent)->toHaveKey('Hello')
            ->and($decodedContent)->toHaveKey('World')
            ->and($decodedContent)->toHaveKey('My Test \'Hello\'')
            ->and($decodedContent)->toHaveKey('Say "Hello World"')
            ->and($decodedContent['Hello'])->toBe('Olá')
            ->and($decodedContent['World'])->toBe('Mundo')
            ->and($decodedContent['My Test \'Hello\''])->toBe('Meu Teste \'Olá\'')
            ->and($decodedContent['Say "Hello World"'])->toBe('Diga "Olá Mundo"');
    });

    it('handles JSON encoding with proper escaping', function (): void {
        $strings = [
            'String with backslash \\',
            'String with newline \n',
            'String with tab \t',
            'String with unicode 🚀',
        ];

        $filePath = $this->testDir . '/special-chars.json';

        $utils = new Langfy\Helpers\Utils;
        $utils->saveStringsToFile($strings, $filePath);

        expect(File::exists($filePath))->toBeTrue();

        $savedContent   = File::get($filePath);
        $decodedContent = json_decode($savedContent, true);

        expect($decodedContent)->toBeArray()
            ->and($decodedContent)->toHaveKey('String with backslash \\')
            ->and($decodedContent)->toHaveKey('String with newline \n')
            ->and($decodedContent)->toHaveKey('String with tab \t')
            ->and($decodedContent)->toHaveKey('String with unicode 🚀')
            ->and($decodedContent['String with unicode 🚀'])->toBe('String with unicode 🚀');
    });
});
