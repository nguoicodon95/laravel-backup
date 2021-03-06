<?php

namespace Spatie\Backup\Test\Unit;

use Spatie\Backup\Exceptions\CannotCreateDbDumper;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\Backup\Test\Integration\TestCase;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;

class DbDumperFactoryTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->app['config']->set('database.default', 'mysql');

        $dbConfig = [
            'driver' => 'mysql',
            'host' => 'localhost',
            'username' => 'root',
            'password' => 'myPassword',
            'database' => 'myDb',
            'dump' => ['add_extra_option' => '--extra-option=value'],
        ];

        $this->app['config']->set('database.connections.mysql', $dbConfig);
    }

    /** @test */
    public function it_can_create_instances_of_mysql_and_pgsql()
    {
        $this->assertInstanceOf(MySql::class, DbDumperFactory::createFromConnection('mysql'));
        $this->assertInstanceOf(PostgreSql::class, DbDumperFactory::createFromConnection('pgsql'));
    }

    /** @test */
    public function it_will_throw_an_exception_when_creating_an_unknown_type_of_dumper()
    {
        $this->expectException(CannotCreateDbDumper::class);

        DbDumperFactory::createFromConnection('unknown type');
    }

    /** @test */
    public function it_can_add_named_options_to_the_dump_command()
    {
        $dumpConfig = ['use_single_transaction'];

        $this->app['config']->set('database.connections.mysql.dump', $dumpConfig);

        $this->assertContains('--single-transaction', $this->getDumpCommand());
    }

    /** @test */
    public function it_can_add_named_options_with_an_array_value_to_the_dump_command()
    {
        $dumpConfig = ['include_tables' => ['table1', 'table2']];

        $this->app['config']->set('database.connections.mysql.dump', $dumpConfig);

        $this->assertContains(implode(' ', $dumpConfig['include_tables']), $this->getDumpCommand());
    }

    /** @test */
    public function it_can_add_arbritrary_options_to_the_dump_command()
    {
        $dumpConfig = ['add_extra_option' => '--extra-option=value'];

        $this->app['config']->set('database.connections.mysql.dump', $dumpConfig);

        $this->assertContains($dumpConfig['add_extra_option'], $this->getDumpCommand());
    }

    protected function getDumpCommand(): string
    {
        $dumpFile = '';
        $credentialsFile = '';

        return DbDumperFactory::createFromConnection('mysql')->getDumpCommand($dumpFile, $credentialsFile);
    }
}
