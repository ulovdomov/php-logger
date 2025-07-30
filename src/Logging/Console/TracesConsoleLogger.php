<?php declare(strict_types = 1);

namespace UlovDomov\Logging\Console;

use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use UlovDomov\Logging\OpenTelemetry\Traces\Span;
use UlovDomov\Logging\OpenTelemetry\Traces\Tracer;

final class TracesConsoleLogger implements EventSubscriberInterface
{
    private Span|null $span = null;

    public function __construct(private readonly Tracer $tracer)
    {

    }

    public function start(Command|null $command = null): void
    {
        if (!$this->tracer->isEnabled()) {
            $this->tracer->enable();
        }

        $rawArgs = \array_slice($_SERVER['argv'], 2);

        $this->span = $this->tracer->startSpan('Console.command', [
            'console_command' => $command?->getName() ?? $_SERVER['argv'][1] ?? '<no-command>',
            'console_arguments' => \implode(' ', $rawArgs),
        ]);
    }

    public function recordException(\Throwable $throwable): void
    {
        $this->getSpan()->recordException($throwable);
        $this->getSpan()->setStatus(StatusCode::STATUS_ERROR);
    }

    public function end(): void
    {
        $this->getSpan()->end();
        $this->tracer->end();
    }

    public function setExitCode(int $exitCode): void
    {
        $this->getSpan()->setAttribute('command_exit_code', $exitCode);
    }

    public function getSpan(): Span
    {
        if ($this->span === null) {
            throw new \LogicException('Logger is not created. Call method start() first.');
        }

        return $this->span;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onCommand',
            ConsoleEvents::TERMINATE => 'onTerminate',
            ConsoleEvents::ERROR => 'onError',
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $this->start($event->getCommand());
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        $this->setInterruptingSignal($event->getInterruptingSignal());
        $this->setExitCode($event->getExitCode());
        $this->end();
    }

    public function onError(ConsoleErrorEvent $event): void
    {
        $this->setExitCode($event->getExitCode());
        $this->recordException($event->getError());
        $this->end();
    }

    private function setInterruptingSignal(int|null $signal): void
    {
        if ($signal !== null) {
            $this->getSpan()->setAttribute('command_interrupt_signal', $signal);
        }
    }
}
