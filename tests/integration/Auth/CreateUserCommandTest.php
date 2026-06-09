<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

use function in_array;

final class CreateUserCommandTest extends KernelTestCase
{
    private CommandTester $tester;
    private UserRepository $users;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $users = $container->get("test." . UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $this->users = $users;

        $command = (new Application(self::$kernel))->find("app:user:create");
        $this->tester = new CommandTester($command);
    }

    public function testCreatesAdminWithVerifiableHash(): void
    {
        $exitCode = $this->tester->execute([
            "--email" => "admin@netpulse.test",
            "--password" => "verysecret123",
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $user = $this->users->byEmail(new Email("admin@netpulse.test"));
        self::assertInstanceOf(User::class, $user);
        self::assertSame("admin@netpulse.test", $user->email()->value());
        self::assertTrue(in_array("ROLE_ADMIN", $user->roles()->toStringArray(), true));

        self::assertNotSame("verysecret123", $user->password()->value());
        self::assertTrue($this->hasher()->verify($user->password()->value(), "verysecret123"));
    }

    public function testDuplicateEmailFailsWithFriendlyMessageAndNoStackTrace(): void
    {
        $this->tester->execute([
            "--email" => "admin@netpulse.test",
            "--password" => "verysecret123",
        ]);

        $exitCode = $this->tester->execute([
            "--email" => "admin@netpulse.test",
            "--password" => "anothersecret123",
        ]);

        self::assertNotSame(Command::SUCCESS, $exitCode);

        $display = $this->tester->getDisplay();
        self::assertStringContainsStringIgnoringCase("already exists", $display);

        self::assertStringNotContainsString("Stack trace", $display);
        self::assertStringNotContainsString("HandlerFailedException", $display);
    }

    public function testWeakPasswordFailsAndCreatesNoUser(): void
    {
        $exitCode = $this->tester->execute([
            "--email" => "weak@netpulse.test",
            "--password" => "short",
        ]);

        self::assertNotSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString("at least 12 characters", $this->tester->getDisplay());
        self::assertNull($this->users->byEmail(new Email("weak@netpulse.test")));
    }

    public function testInvalidEmailFailsWithFriendlyMessage(): void
    {
        $exitCode = $this->tester->execute([
            "--email" => "not-an-email",
            "--password" => "verysecret123",
        ]);

        self::assertNotSame(Command::SUCCESS, $exitCode);
        self::assertStringNotContainsString("Stack trace", $this->tester->getDisplay());
    }

    private function hasher(): PasswordHasherInterface
    {
        $factory = self::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $factory);

        return $factory->getPasswordHasher(PasswordAuthenticatedUserInterface::class);
    }
}
