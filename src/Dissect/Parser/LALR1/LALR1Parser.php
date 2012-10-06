<?php

namespace Dissect\Parser\LALR1;

use Dissect\Lexer\TokenStream\TokenStream;
use Dissect\Parser\Exception\UnexpectedTokenException;
use Dissect\Parser\LALR1\Analysis\Analyzer;
use Dissect\Parser\Grammar;
use Dissect\Parser\Parser;

/**
 * A parser accepting a LALR(1) parse table.
 *
 * @author Jakub Lédl <jakubledl@gmail.com>
 */
class LALR1Parser implements Parser
{
    /**
     * @var \Dissect\Parser\Grammar
     */
    protected $grammar;

    /**
     * @var array
     */
    protected $parseTable;

    /**
     * Constructor.
     *
     * @param \Dissect\Parser\Grammar $grammar The grammar.
     * @param array $parseTable If given, the parser doesn't have to analyze the grammar.
     */
    public function __construct(Grammar $grammar, array $parseTable = null)
    {
        $this->grammar = $grammar;

        if ($parseTable) {
            $this->parseTable = $parseTable;
        } else {
            $analyzer = new Analyzer();
            $this->parseTable = $analyzer->createParseTable($grammar);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function parse(TokenStream $stream)
    {
        $stateStack = array(0);
        $currentState = 0;
        $args = array();

        foreach ($stream as $token) {
            while (true) {
                $type = $token->getType();

                if (!isset($this->parseTable['action'][$currentState][$type])) {
                    // unexpected token
                    throw new UnexpectedTokenException(
                        $token,
                        array_keys($this->parseTable['action'][$currentState])
                    );
                }

                $action = $this->parseTable['action'][$currentState][$type];

                if ($action > 0) {
                    // shift
                    $args[] = $token->getValue();
                    $stateStack[] = $currentState = $action;

                    break;
                } elseif ($action < 0) {
                    // reduce
                    $rule = $this->grammar->getRule(-$action);
                    $popCount = count($rule->getComponents());
                    $newArgs = array();

                    for ($i = 0; $i < $popCount; $i++) {
                        array_pop($stateStack);
                        array_unshift($newArgs, array_pop($args));
                    }

                    if ($callback = $rule->getCallback()) {
                        $args[] = call_user_func_array($callback, $newArgs);
                    } else {
                        $args[] = $newArgs[0];
                    }

                    $state = $stateStack[count($stateStack) - 1];
                    $stateStack[] = $currentState = $this->parseTable['goto']
                        [$state][$rule->getName()];
                } elseif ($action === 'acc') {
                    return $args[0];
                }
            }
        }
    }
}
