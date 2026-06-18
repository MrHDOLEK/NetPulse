<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Auth\Application\Command\CreateAdmin\CreateAdminCommand;
use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class ResetPasswordCommandTest extends KernelTestCase
{
    private const string EMAIL = 'admin@netpulse.test';
    private const string ORIGINAL_PASSWORD = 'originalsecret123';

    private CommandTester $tester;
    private UserRepository $users;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $users = $container->get('test.' . UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $this->users = $users;

        $command = new Application(self::$kernel)->find('app:user:reset-password');
        $this->tester = new CommandTester($command);
    }

    public function testResetsPasswordSoNewHashVerifies(): void
    {
        $this->seedUser();

        $exitCode = $this->tester->execute([
            '--email' => self::EMAIL,
            '--password' => 'brandnewsecret123',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $user = $this->users->byEmail(new Email(self::EMAIL));
        self::assertInstanceOf(User::class, $user);

        $hasher = $this->hasher();
        self::assertTrue($hasher->verify($user->password()->value(), 'brandnewsecret123'));
        self::assertFalse($hasher->verify($user->password()->value(), self::ORIGINAL_PASSWORD));
    }

    public function testUnknownEmailFailsWithFriendlyMessage(): void
    {
        $exitCode = $this->tester->execute([
            '--email' => 'ghost@netpulse.test',
            '--password' => 'brandnewsecret123',
        ]);

        self::assertNotSame(Command::SUCCESS, $exitCode);

        $display = $this->tester->getDisplay();
        self::assertStringContainsStringIgnoringCase('not found', $display);
        self::assertStringNotContainsString('Stack trace', $display);
        self::assertStringNotContainsString('HandlerFailedException', $display);
    }

    public function testWeakPasswordFailsWithFriendlyMessage(): void
    {
        $this->seedUser();

        $exitCode = $this->tester->execute([
            '--email' => self::EMAIL,
            '--password' => 'short',
        ]);

        self::assertNotSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('at least 12 characters', $this->tester->getDisplay());
    }

    private function seedUser(): void
    {
        $bus = self::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);
        $bus->dispatch(new CreateAdminCommand(self::EMAIL, self::ORIGINAL_PASSWORD));
    }

    private function hasher(): PasswordHasherInterface
    {
        $factory = self::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $factory);

        return $factory->getPasswordHasher(PasswordAuthenticatedUserInterface::class);
    }
}
