<?php

namespace Hammerstone\Sidecar\PHP\Tests\Unit;

class WorkJobTest extends BaseTest
{
    /** @test */
    public function it_will_not_run_on_lambda_when_the_sidecar_queues_feature_is_off()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function it_will_not_bind_the_extended_queue_worker_when_the_sidecar_queues_feature_is_off()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function it_will_not_run_on_lambda_when_opt_in_is_required_where_payload_is_opted_in_and_opted_out()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function it_will_not_run_on_lambda_when_opt_in_is_required_where_payload_is_not_opted_in_and_opted_out()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function it_will_not_run_on_lambda_when_opt_in_is_required_where_payload_is_not_opted_in_and_not_opted_out()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function it_will_run_on_lambda_when_opt_in_is_required_where_payload_is_opted_in_and_not_opted_out()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function it_will_not_run_on_lambda_when_opt_in_is_not_required_where_payload_is_opted_in_and_opted_out()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function it_will_not_run_on_lambda_when_opt_in_is_not_required_where_payload_is_not_opted_in_and_opted_out()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function it_will_run_on_lambda_when_opt_in_is_not_required_where_payload_is_not_opted_in_and_not_opted_out()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function it_will_run_on_lambda_when_opt_in_is_not_required_where_payload_is_opted_in_and_not_opted_out()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function when_the_lambda_flags_its_job_as_deleted_then_the_job_is_deleted()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function when_the_lambda_flags_its_job_as_not_deleted_then_the_job_is_not_deleted()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function when_the_lambda_flags_its_job_as_released_then_the_job_is_released()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function when_the_lambda_flags_its_job_as_not_released_then_the_job_is_not_released()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function when_the_lambda_flags_its_job_as_released_with_a_custom_delay_then_the_job_is_released_with_the_same_custom_delay()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function when_the_lambda_throws_an_exception_then_the_job_is_marked_as_failed_and_released_for_retry()
    {
        $this->markTestIncomplete('do better');
    }

    /** @test */
    public function it_will_not_run_on_lambda_when_the_job_has_hit_its_max_attempts_and_the_job_will_fail_as_normal()
    {
        $this->markTestIncomplete('do better');
    }
}
