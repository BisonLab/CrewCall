<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use App\Entity\Job;
use App\Entity\Person;
use App\Lib\ExternalEntityConfig;

/**
 *
 */
class JobRepository extends ServiceEntityRepository
{
    private $params;

    public function __construct(ManagerRegistry $registry, ParameterBagInterface $params)
    {
        $this->params = $params;
        parent::__construct($registry, Job::class);
    }

    /*
     * Find'em all, or fewer.
     */
    public function findJobs($options = [])
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('j')
            ->from($this->_entityName, 'j')
            ->innerJoin('j.shift', 's');

        // This only halfly works, since it doesen't get the children events.
        // Nesting more than one level of parent/child feels messy.
        // (At least until someone with more DQL-fu shows me an effective way)
        if (isset($options['events']) || isset($options['event_states'])) {
            $qb->innerJoin('s.event', 'e');
            if (isset($options['events'])) 
                $qb->andWhere('e.id in (:events)')
                ->setParameter('events', $options['events']);
            if (isset($options['event_states']))
                $qb->andWhere('e.state in (:event_states)')
                ->setParameter('event_states', $options['event_states']);
            // If there are no from or to set, set them far far away.
            // Easier than hacking the from date stuff at the end here.
            if (!isset($options['from']))
                $options['from'] = "2000-01-01";
            if (!isset($options['to']))
                $options['to'] = "3000-01-01";
        }

        if (isset($options['shift_states'])) {
            if (isset($options['shift_states']))
                $qb->andWhere('s.state in (:shift_states)')
                ->setParameter('shift_states', $options['shift_states']);
        }

        if (isset($options['functions'])) {
            if (isset($options['functions'])) 
                $qb->andWhere('s.function in (:functions)')
                ->setParameter('functions', $options['functions']);
        }

        // I do not want UNINTERESTED (And i wish I never made that state)
        if (isset($options['state'])) {
            $qb->andWhere('j.state = :state')
            ->setParameter('state', $options['state']);
        } elseif (!isset($options['states'])) {
            $qb->andWhere('j.state != :state')
            ->setParameter('state', 'UNINTERESTED');
        }

        if (isset($options['states']) && !empty($options['states'])) {
            $qb->andWhere('j.state in (:states)')
            ->setParameter('states', $options['states']);
        }

        // Yeah, time to agree with yourself Thomas, people or  persons?
        if (isset($options['persons']) && !empty($options['persons'])) {
            $qb->andWhere('j.person in (:persons)')
            ->setParameter('persons', $options['persons']);
        }

        if ($options['wishlist'] ?? false) {
            $states = ExternalEntityConfig::getWishlistStatesFor('Job');
            $qb->andWhere('j.state in (:states)')
            ->setParameter('states', $states);
        }

        if ($options['booked'] ?? false) {
            $states = ExternalEntityConfig::getBookedStatesFor('Job');
            $qb->andWhere('j.state in (:states)')
                ->setParameter('states', $states);
        }

        if ($options['past'] ?? false) {
            if (!isset($options['to'])) {
                $to = new \DateTime();
                $qb->andWhere('s.end <= :to')
                    ->setParameter('to', $to);
            }
        }

        // Unless there are a set timeframe, use "from now".
        $from = new \DateTime();
        // And here it can be overridden
        if (isset($options['from']) || isset($options['to'])) {
            if (isset($options['from'])) {
                if ($options['from'] instanceof \DateTime )
                    $from = $options['from'];
                else
                    $from = new \DateTime($options['from']);
            }
            if (isset($options['to'])) {
                if ($options['to'] instanceof \DateTime )
                    $to = $options['to'];
                else
                    $to = new \DateTime($options['to']);
                // And since it's "Include to", add one day and use less than
                // and it'll include any time the day we wanted.
                $qb->andWhere('s.start < :to')
                   ->setParameter('to', $to->modify("+1 day"));
            }
        }
        // This makes the result not include jobs started the day before but
        // ending the from-day.
        if ($options['without_rollover'] ?? false)
            $qb->andWhere('s.start >= :from')->setParameter('from', $from);
        else
            $qb->andWhere('s.end >= :from')->setParameter('from', $from);
        $qb->orderBy('s.start', 'ASC');
        return $qb->getQuery()->getResult();
    }

    /*
     * Job queries for person
     */
    public function findJobsForPerson(Person $person, $options)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('j')
            ->from($this->_entityName, 'j')
            ->innerJoin('j.shift', 's')
            ->where("j.person = :person")
            ->setParameter('person', $person);

        if (isset($options['state'])) {
            $qb->andWhere('j.state = :state')
            ->setParameter('state', $options['state']);
        } elseif (!isset($options['states'])) {
            $qb->andWhere('j.state != :state')
            ->setParameter('state', 'UNINTERESTED');
        }

        if (isset($options['states'])) {
            $qb->andWhere('j.state in (:states)')
            ->setParameter('states', $options['states']);
        }

        if ($options['wishlist'] ?? false) {
            $states = ExternalEntityConfig::getWishlistStatesFor('Job');
            $qb->andWhere('j.state in (:states)')
            ->setParameter('states', $states);
        }

        if ($options['booked'] ?? false) {
            $states = ExternalEntityConfig::getBookedStatesFor('Job');
            $qb->andWhere('j.state in (:states)')
                ->setParameter('states', $states);
            if (!isset($options['from'])) {
                $from = new \DateTime();
                $qb->andWhere('s.end >= :from')
                    ->setParameter('from', $from);
            }
        }

        // Unless there are a set timeframe, use "from now".
        $from = new \DateTime();

        if ($options['on_date'] ?? false) {
            $on_date = $options['on_date'];
            if (!$on_date instanceOf \DateTime)
                $on_date = new \DateTime($on_date);
            // Kinda cheating.
            $options['from'] = $on_date;
            $options['to'] = clone($on_date);
        }

        // And from(!) here it can be overridden

        if ($options['past'] ?? false) {
            // If the "to" option is set, use it and not "past".
            if (!isset($options['to'])) {
                $to = new \DateTime();
                $qb->andWhere('s.end <= :to')
                    ->setParameter('to', $to);
            }
            // More or less a random day on the past. If we need to use
            // specified from dates, it will be set further below.
            $from = new \DateTime("2018-01-01");
        }

        if (($options['from'] ?? false) || ($options['to'] ?? false)) {
            if (isset($options['from'])) {
                if ($options['from'] instanceof \DateTime )
                    $from = $options['from'];
                else
                    $from = new \DateTime($options['from']);
            }
            if (isset($options['to'])) {
                if ($options['to'] instanceof \DateTime )
                    $to = $options['to'];
                else
                    $to = new \DateTime($options['to']);
                // Gotta include the whole day since this is DateTime.
                $qb->andWhere('s.start <= :to')
                   ->setParameter('to', $to->setTime(23,59));
            }
        }
        // Either the default or what's set above.
        $qb->andWhere('s.end >= :from')->setParameter('from', $from);
        $qb->orderBy('s.start', 'ASC');
        return $qb->getQuery()->getResult();
    }

    /*
     * TODO: Add timeframe and default with from now
     */
    public function findByStateForPerson(Person $person, $options)
    {
        $state = $options['state'];
        $qb = $this->_em->createQueryBuilder();
        $qb->select('j')
            ->from($this->_entityName, 'j')
            ->where('j.state = :state')
            ->andWhere("j.person = :person")
            ->setParameter('state', $state)
            ->setParameter('person', $person);

        if (isset($options['from']) || isset($options['to'])) {
            $qb->innerJoin('j.shift', 's');
            // Unless there are a set timeframe, use "from now".
            $from = new \DateTime();
            if (isset($options['from'])) {
                if ($options['from'] instanceof \DateTime )
                    $from = $options['from'];
                else
                    $from = new \DateTime($options['from']);
            }
            $qb->andWhere('s.start >= :from')
               ->setParameter('from', $from);

            if (isset($options['to'])) {
                if ($options['to'] instanceof \DateTime )
                    $to = $options['to'];
                else
                    $to = new \DateTime($options['to']);
                $qb->andWhere('s.start < :to')
                   ->setParameter('to', $to->modify("+1 day"));
            }
        }
        $qb->orderBy('s.end', 'DESC');
        return $qb->getQuery()->getResult();
    }

    /*
     * Hmm, need it, gotta find out best way to to.
     * TODO: Ponder how I can configure overlap requirements. Should be a
     * configuration option for "Overlap check method". Both here and the one
     * that sets the overlap flag. (Which isn't very useful right now.)
     */
    public function checkOverlapForPerson($job, $options = [])
    {
        $person = $job->getPerson();
        $jobstart = $job->getStart();
        $jobend = $job->getEnd();
        $qb = $this->_em->createQueryBuilder();
        $qb->select('j')
            ->from($this->_entityName, 'j')
            ->innerJoin('j.shift', 's')
            ->andWhere("j.person = :person")
            ->setParameter('person', $person);

        /*
         * Options/Params/config. More or less useful.
         */
        $overlap_hours = $this->params->get('job_overlap_hours') ?: 0;
        if ($this->params->get('job_overlap_same_day')) {
            $from_day = clone($jobstart);
            // This just looks so wrong.
            $qb
                ->andWhere('s.start >= :from_day_start')
                ->andWhere('s.start <= :from_day_end')
                ->setParameter('from_day_start', $from_day->format("Y-m-d 00:00"))
                ->setParameter('from_day_end', $from_day->format("Y-m-d 23:59"))
            ;
        } elseif ($overlap_hours == 0) {
            $qb
                ->andWhere('s.start <= :to')
                ->andWhere('s.end >= :from')
                ->setParameter('to', $jobend)
                ->setParameter('from', $jobstart)
            ;
        }

        if ($overlap_hours > 0) {
            // Gotta extend the time period to check overlap for to
            // ±overlap_hours
            $ft = clone($jobstart);
            $fromtime = $ft->modify("-" . $overlap_hours . " hours");
            $t = clone($jobend);
            $totime = $t->modify("+" . $overlap_hours . " hours");
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->between('s.start', ':fromtime', ':totime'),
                    $qb->expr()->between('s.end', ':fromtime', ':totime')
               ) );
            $qb->setParameter('fromtime', $fromtime);
            $qb->setParameter('totime', $totime);
        }

        if ($this->params->get('job_overlap_booked_only')) {
            $states = ExternalEntityConfig::getBookedStatesFor('Job');
            $qb->andWhere('j.state in (:states)')
                ->setParameter('states', $states);
        }

        $result = new ArrayCollection($qb->getQuery()->getResult());
        $one_booked = $job->isBooked();
        $a = [];
        foreach ($result as $j) {
            if ($j->getId() != $job->getId()) {
                $a[] = $j;
                if ($j->isBooked())
                    $one_booked = true;
            }
        }
        if ($options['any_overlap'] ?? false) {
            if ($options['return_jobs'] ?? false) {
                return $a;
            }
            return count($a) > 0;
        }
        if ($options['return_jobs'] ?? false) {
            if ($one_booked)
                return $a;
            else
                return [];
        }
        if ($one_booked)
            return count($a) > 0;
        else
            return false;
    }
}
