<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

use App\Repository\PersonRepository;
use App\Entity\PersonContext;
use App\Entity\PersonState;
use App\Entity\EmbeddableAddress;
use App\Entity\FunctionEntity;
use App\Lib\ExternalEntityConfig;

/**
 * @ORM\Entity
 * @ORM\Table(name="crewcall_person")
 * @ORM\Entity(repositoryClass=PersonRepository::class)
 * @UniqueEntity(fields={"username", "email"}, message="There is already an account with this username or email address")
 * @Gedmo\Loggable
 */
class Person implements UserInterface
{
    use \BisonLab\CommonBundle\Entity\AttributesTrait;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     * @Assert\NotBlank
     */
    private $username;

    /**
     * @ORM\Column(type="json")
     */
    private $system_roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     */
    private $password;

    /**
     * Could be based on state. Maybe should?
     * @ORM\Column(type="boolean")
     */
    private $is_verified = false;

    /**
     * @ORM\Column(type="boolean")
     */
    private $enabled = true;

    /**
     * @var datetime Last Login
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $last_login;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     * @Assert\NotBlank
     * @Assert\Email
     */
    private $email;

    /**
     * @var string
     * @ORM\Column(name="first_name", type="string", length=255, nullable=true)
     * @Gedmo\Versioned
     * @Assert\NotBlank
     */
    private $first_name;

    /**
     * @var string
     * @ORM\Column(name="last_name", type="string", length=255, nullable=true)
     * @Gedmo\Versioned
     * @Assert\NotBlank
     */
    private $last_name;

    /**
     * @var string
     * @ORM\Column(name="full_name", type="string", length=255, nullable=true)
     *
     * This one is not to be set by anything else than this Entity.
     */
    private $full_name;

    /**
     * Looks odd, but age may be quite useful in many cases.
     * @var string
     * @ORM\Column(name="date_of_birth", type="date", length=255, nullable=true)
     * @Gedmo\Versioned
     */
    private $date_of_birth;

    /**
     * Another odd one, but it's an increasingly hot topic.
     * @var string
     * @ORM\Column(name="diets", type="array", length=255, nullable=true)
     * @Gedmo\Versioned
     */
    private $diets;

    /**
     * @var string
     * @ORM\Column(name="mobile_phone_number", type="string", length=255, nullable=true)
     * @Gedmo\Versioned
     */
    private $mobile_phone_number;

    /**
     * The last of two phone numbers.
     * Two should be enough for a table, the rest should be added as 
     * attributes, same with Facebook/Google usernames/addresses 
     *
     * @var string
     * @ORM\Column(name="home_phone_number", type="string", length=255, nullable=true)
     * @Gedmo\Versioned
     */
    private $home_phone_number;

    /**
     * @ORM\Embedded(class="EmbeddableAddress")
     **/
    private $address;

    /**
     * @ORM\Embedded(class="EmbeddableAddress")
     **/
    private $postal_address;

    /**
     * This is for the non-connected functions. (Skills)
     * @ORM\OneToMany(targetEntity="PersonFunction", mappedBy="person", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $person_functions;

    /**
     * This is really functions, but since we have three (four) ways for a
     * function to be connected to this Person object we have to define each
     * by the other end of the person_role_ connection.
     * @ORM\OneToMany(targetEntity="PersonRoleOrganization", mappedBy="person", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $person_role_organizations;

    /**
     * This is really functions, but since we have three (four) ways for a
     * function to be connected to this Person object we have to define each
     * by the other end of the person_role_ connection.
     * @ORM\OneToMany(targetEntity="PersonRoleEvent", mappedBy="person", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $person_role_events;

    /**
     * And again!
     * @ORM\OneToMany(targetEntity="PersonRoleLocation", mappedBy="person", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $person_role_locations;

    /**
     * This is for the actual jobs.
     * @ORM\OneToMany(targetEntity="Job", mappedBy="person", fetch="EXTRA_LAZY", cascade={"remove"})
     */
    private $jobs;

    /**
     * This is for states. A person shall only be able to have one at all
     * time, but we need the history and need to set states in the future
     * (Vacation)
     * @ORM\OneToMany(targetEntity="PersonState", mappedBy="person", fetch="LAZY", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"from_date" = "ASC"})
     */
    private $person_states;

    /**
     * @ORM\OneToMany(targetEntity="PersonContext", mappedBy="owner", cascade={"persist", "remove", "merge"}, orphanRemoval=true)
     */
    private $contexts;

    public function __construct()
    {
        $this->last_login = new \DateTime();
        $this->person_functions = new ArrayCollection();
        $this->person_role_organizations = new ArrayCollection();
        $this->person_role_locations = new ArrayCollection();
        $this->person_role_events = new ArrayCollection();
        $this->contexts  = new ArrayCollection();
        $this->jobs  = new ArrayCollection();
        $this->person_states  = new ArrayCollection();
        $this->address = new EmbeddableAddress();
        $this->postal_address = new EmbeddableAddress();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $system_roles = $this->system_roles;
        // guarantee every user at least has ROLE_USER
        $system_roles[] = 'ROLE_USER';

        return array_unique($system_roles);
    }

    public function setRoles(array $system_roles): self
    {
        $this->system_roles = $system_roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }


    /**
     * Set first_name
     *
     * @param string $first_name
     *
     * @return Person
     */
    public function setFirstName($first_name)
    {
        $this->first_name = $first_name;
        $this->setFullName();

        return $this;
    }

    /**
     * Get first_name
     *
     * @return string
     */
    public function getFirstName()
    {
        return $this->first_name;
    }

    /**
     * Set last_name
     *
     * @param string $last_name
     *
     * @return Person
     */
    public function setLastName($last_name)
    {
        $this->last_name = $last_name;
        $this->setFullName();

        return $this;
    }

    /**
     * Get last_name
     *
     * @return string
     */
    public function getLastName()
    {
        return $this->last_name;
    }

    // Concatenate the two above. Looks odd, but we do store the full name in
    // the database so we gotta do it like this.
    private function setFullName()
    {
        $this->full_name =  $this->getFirstName() . " " . $this->getLastName();
    }

    public function getName()
    {
        return $this->full_name ?: $this->getUserName();
    }

    public function getFullName()
    {
        return $this->full_name ?: $this->getUserName();
    }

    /**
     * Set date_of_birth
     *
     * @param string $date_of_birth
     *
     * @return Person
     */
    public function setDateOfBirth($date_of_birth)
    {
        $this->date_of_birth = $date_of_birth;

        return $this;
    }

    /**
     * Get date_of_birth
     *
     * @return string
     */
    public function getDateOfBirth()
    {
        return $this->date_of_birth;
    }

    /**
     * Set diets
     *
     * @param string $diets
     *
     * @return Person
     */
    public function setDiets($diets)
    {
        $this->diets = $diets;

        return $this;
    }

    /**
     * Get diets
     *
     * @return string
     */
    public function getDiets()
    {
        return $this->diets ?: array();
    }

    /**
     * Get diets
     *
     * @return string
     */
    public function getDietsLabels()
    {
        $labels = array();
        $dtypes = ExternalEntityConfig::getTypesFor('Person', 'Diet');
        foreach ($this->getDiets() as $d) {
            $labels[] = $dtypes[$d]['label'];
        }
        return $labels;
    }

    /*
     * I'll use "DietTypes" here since I store the options in types.yml.
     */
    public static function getDietTypes()
    {
        return array_keys(ExternalEntityConfig::getTypesFor('Person', 'Diet'));
    }

    public static function getDietTypesAsChoiceArray()
    {
        return ExternalEntityConfig::getTypesAsChoicesFor('Person', 'Diet');
    }

    /**
     * Set mobilePhoneNumber
     *
     * @param string $mobilePhoneNumber
     *
     * @return Person
     */
    public function setMobilePhoneNumber($mobilePhoneNumber)
    {
        $this->mobile_phone_number = $mobilePhoneNumber;

        return $this;
    }

    /**
     * Get mobilePhoneNumber
     *
     * @return string
     */
    public function getMobilePhoneNumber()
    {
        return $this->mobile_phone_number;
    }

    /**
     * Set homePhoneNumber
     *
     * @param string $homePhoneNumber
     *
     * @return Person
     */
    public function setHomePhoneNumber($homePhoneNumber)
    {
        $this->home_phone_number = $homePhoneNumber;

        return $this;
    }

    /**
     * Get homePhoneNumber
     *
     * @return string
     */
    public function getHomePhoneNumber()
    {
        return $this->home_phone_number;
    }

    /**
     * Set Address
     *
     * @param string $Address
     *
     * @return Person
     */
    public function setAddress(EmbeddableAddress $Address)
    {
        $this->address = $Address;

        return $this;
    }

    /**
     * Get Address
     *
     * @return \App\Entity\EmbeddableAddress
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set postalAddress
     *
     * @param string $postalAddress
     *
     * @return Person
     */
    public function setPostalAddress(EmbeddableAddress $postalAddress)
    {
        $this->postal_address = $postalAddress;

        return $this;
    }

    /**
     * Get postalAddress
     *
     * @return string
     */
    public function getPostalAddress()
    {
        return $this->postal_address;
    }

    /**
     * Set state
     *
     * Way too complex. I am trying to squeeze states inside states and make
     * sure we only have one current state and so on.
     *
     * TODO:
     * I'd better come up with a simpler solution, maybe even let the admin
     * users take care of the date handling and accept there will be two states
     * for the same day when they don't.
     *
     * With a default state there should at least be something returned from
     * getState anyway.
     *
     * But there should be a saved state for any given date, just for the
     * record. So, I should be setting some dates, and woops, complex again.
     *
     * @param string $state
     * @return Person
     */
    public function setState($state, $options = array())
    {
        // No dates and same state? no need to go on.
        if (empty($options) && $state == $this->getState())
            return $this;
            
        $newstate = new PersonState();
        $newstate->setState($state);
        $curstate = $this->getStateOnDate();

        if (empty($options)) {
            $newstate->setFromDate(new \DateTime());
            if ($curstate) {
                // Just in case the state was set today.
                if ($curstate->getFromDate() == $newstate->getFromDate())
                    $this->removeState($curstate);
                else
                    // Can't be in the future, so set the to date to yesterday.
                    $curstate->setToDate(new \DateTime('yesterday'));
            }
            $this->addState($newstate);
            return $this;
        }

        if (isset($options['from_date'])) {
            $newstate->setFromDate($options['from_date']);
        } else {
            $newstate->setFromDate(new \DateTime());
        }

        if (isset($options['to_date'])) {
            $newstate->setToDate($options['to_date']);
        }

        // Find out if we have to inject the state or whatever.
        // Before is the current one relative to the from date of the new state.
        $before = null;
        $after  = null;
        foreach ($this->person_states as $ps) {
            // Get rid of oldies and Too newdies
            if ($ps->getToDate() !== null
                    && $ps->getToDate() < $newstate->getFromDate()) {
                continue;
            }

            // If this state is within the new state, just remove it.
            if ($newstate->getFromDate() <= $ps->getFromDate()) {
                if (!$ps->getToDate() && !$newstate->getToDate()) {
                    $this->removeState($ps);
                    continue;
                }
                if ($ps->getToDate() <= $newstate->getToDate()) {
                    $this->removeState($ps);
                    continue;
                }
            }

            // New state in the future after this period?
            // This could be the "After" one, but it does not count since it's
            // not within the period we are setting this.
            if ($newstate->getToDate() && $ps->getFromDate() > $newstate->getToDate()) {
                continue;
            }

            if ($ps->getFromDate() < $newstate->getFromDate()) {
                // Are we (not) closer?
                if ($before && $before->getFromDate() < $ps->getFromDate())
                    continue;
                $before = $ps;
            }

            if ($ps->getToDate() > $newstate->getToDate()) {
                // Are we (not) closer?
                if ($after && ($after->getToDate() < $ps->getToDate() || $ps->getToDate() == null))
                    continue;
                $after = $ps;
            }
        }

        $this->addState($newstate);
        // Do we have to insert the new state into the before?
        if ($before && $before === $after) {
            $afterstate = new PersonState();
            $afterstate->setState($before->getState());
            if ($newstate->getToDate())
                $afterdate = clone($newstate->getToDate());
            else
                $afterdate = new \DateTime();
            $afterstate->setFromDate($afterdate->modify("+1 day"));
            $afterstate->setToDate($before->getToDate());
            $this->addState($afterstate);
        } elseif ($after) {
            $afterdate = clone($newstate->getToDate());
            $after->setFromDate($afterdate->modify("+1 day"));
        } elseif ($before && $newstate->getToDate()) {
            $afterstate = new PersonState();
            $afterstate->setState($before->getState());
            $afterdate = clone($newstate->getToDate());
            $afterstate->setFromDate($afterdate->modify("+1 day"));
            $this->addState($afterstate);
        }
        
        if ($before) {
            $bend = clone($newstate->getFromDate());
            $before->setToDate($bend->modify("-1 day"));
        }
        return $this;
    }

    /*
     * Add a PersonState.
     * Not sure how much validation and functionality I should put here, but 
     * I guess it's the rightest place since this is where everything must
     * go.
     */
    public function addState(PersonState $state)
    {
        // Only validation I care about for now:
        if ($state->getToDate() && $state->getToDate() < $state->getFromDate())
            throw new \InvalidArgumentException("To date on a state can not be before from date"); 
        if (!$this->person_states->contains($state)) {
            if (!$state->getPerson())
                $state->setPerson($this);
            $this->person_states->add($state);
        }
        return $this;
    }

    public function removeState(PersonState $state)
    {
        if ($this->person_states->contains($state))
            $this->person_states->removeElement($state);
        return $this;
    }

    /**
     * Get state
     *
     * @return string
     */
    public function getState()
    {
        return $this->getStateOnDate()->getState();
    }

    /**
     * Get state label
     *
     * @return string 
     */
    public function getStateLabel($state_or_date = null)
    {
        // Pretty simple check, but should just be enough. 
        if ($state_or_date instanceof \DateTime || preg_match('/^\d/', $state_or_date)) {
            $state = (string)$this->getStateOnDate($state_or_date);
        } else {
            $state = $state_or_date ?: $this->getState();
        }
        return ExternalEntityConfig::getStatesFor('Person')[$state]['label'];
    }

    /**
     * Get current state
     * Should use criterias or querybuilder calls for efficiency.
     * But array/doctrine-collection criterias is not really good on dates and
     * repositories nor querybuilders are not to be accessed from entities.
     *
     * @return string
     */
    public function getStateOnDate($date = null)
    {
        if (!$date)
            $date = new \DateTime();
        elseif (!$date instanceOf \DateTime)
            $date = new \DateTime($date);

        foreach ($this->getStates() as $ps) {
            // There is always a from date. Is it in the future?
            if ($ps->getFromDate() > $date)
                continue;
            // But not a to_date.
            if ($ps->getToDate() != null && $ps->getToDate() < $date)
                continue;
            return $ps;
        }
        // Can not return nothing.
        $default = new PersonState();
        $default->setState(ExternalEntityConfig::getDefaultStateFor('Person'));
        $default->setFromDate($date);
        return $default;
    }

    /**
     * If you need more advance filtering than "last_and_next", use the
     * PersonState repository->getByPerson()
     *
     * Return option:
     * - last_and_next - the states before and after this one. 
     *
     * @return hash
     */
    public function getStates($options = [])
    {
        if (empty($options) || !$this->person_states)
            return $this->person_states ?: new ArrayCollection();

        $states = new ArrayCollection();
        $last = null;
        $current = null;
        $next = null;
        $now = new \DateTime();
        foreach ($this->person_states as $ps) {
            if ($ps->getToDate() !== null && $ps->getToDate() < $now) {
                $last = $ps;
                continue;
            }
            // Are we left with the only viable state now?
            if ($current)
                $next = $ps;
            else
                $current = $ps;
            // For now.
            if ($next) break;
        }
        if (isset($options['last_and_next'])) {
            if ($last) $states->add($last);
            if ($current) $states->add($current);
            if ($next) $states->add($next);
        }
        return $states;
    }

    public static function getStatesList()
    {
        return array_keys(ExternalEntityConfig::getStatesFor('Person'));
    }

    /**
     * Get enabled, the override.
     *
     * @return bool 
     */
    public function getEnabled()
    {
        if (in_array($this->getState(),
                ExternalEntityConfig::getEnableLoginStatesFor('Person'))) {
            return true;
        } else {
            return false;
        }
    }

    public function isEnabled()
    {
        /*
         * Fallback, if no state. Which should only occure if you create the
         * users the wrong way. Or in the CLI, when starting the project.
         */
        if (!$this->getState())
            return $this->enabled;

        return $this->getEnabled();
    }

    public function isActive()
    {
        return in_array($this->getState(), ExternalEntityConfig::getActiveStatesFor('Person'));
    }

    public function isAdmin()
    {
        /*
         * Fallback, if no state. Which should only occure if you create the
         * users the wrong way. Or in the CLI, when starting the project.
         */
        return in_array('ROLE_ADMIN', $this->system_roles);
    }

    /*
     * Could be simple yes/no, but can just as well be alot more.
     * Which is why I add options.
     *
     * It should check both states and (booked) jobs but that will be
     * too resource-expensive since job iteself does not have dates and then
     * I'd have to iterate through all jobs with shifts to find the ones
     * matching.
     *
     * * date - On a specific date - Any job that day will return treue
     * * datetime - On a specific date and time - State and job on that time will return true
     * * TODO: from - DateTime for a timeframe 
     * * TODO: to - DateTime for a timeframe
     * * reasons - Will return a list of all reasons for being occupied.
     */
    public function isOccupied($options = [])
    {
        $occupied = false;
        $reason = [];
        // Find a date.
        $time = new \DateTime();
        if (isset($options['datetime']) && $options['datetime'] instanceof \DateTime)
            $time = $options['datetime'];
        elseif (isset($options['datetime']))
            $time = new \DateTime($options['datetime']);
        elseif (isset($options['date']))
            $time = new \DateTime($options['date']);

        /*
         * Check state. I'll default to the uncertain
         */
        $stateobj = $this->getStateOnDate($time);

        $state = $stateobj->getState();
        if (!in_array($state,
                ExternalEntityConfig::getActiveStatesFor('Person'))) {
            $occupied = true;
            $reason['stateobj'] = $stateobj;
            $reason['state'] = $state;
            $reason['statelabel'] = $stateobj->getStateLabel();
        }

        /*
         * Check jobs.
         * TODO: Maybe do it, probaly not. Better handled by job handler.
         */

        /*
         * Return something.
         */
        if ($occupied && isset($options['reason']))
            return $reason;
        else
            return $occupied;
    }

    /*
     * The big "Does this work or not" is wether this getter should include
     * *all* functions. Alas also those in the person_* tables. I say "Yes",
     * but then it's not that easy to handle these functions in
     * picker-forms.
     */

    /**
     * Add personFunction
     *
     * @param \App\Entity\PersonFunction $personFunction
     *
     * @return Person
     */
    public function addPersonFunction(\App\Entity\PersonFunction $personFunction)
    {
        $this->person_functions[] = $personFunction;
        $personFunction->setPerson($this);

        return $this;
    }

    /**
     * Remove personFunction
     *
     * @param \App\Entity\PersonFunction $personFunction
     */
    public function removePersonFunction(\App\Entity\PersonFunction $personFunction)
    {
        $this->person_functions->removeElement($personFunction);
        $personFunction->setPerson(null);
    }

    /**
     * Get personFunctions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPersonFunctions()
    {
        return $this->person_functions;
    }

    /*
     * Roles, basically the same as functions, but connected to Event, Location and Organization.
     */

    /**
     * Add personRoleOrganization
     *
     * @param \App\Entity\PersonRoleOrganization $personRoleOrganization
     *
     * @return Person
     */
    public function addPersonRoleOrganization(\App\Entity\PersonRoleOrganization $personRoleOrganization)
    {
        $this->person_role_organizations[] = $personRoleOrganization;

        return $this;
    }

    /**
     * Remove personRoleOrganization
     *
     * @param \App\Entity\PersonRoleOrganization $personRoleOrganization
     */
    public function removePersonRoleOrganization(\App\Entity\PersonRoleOrganization $personRoleOrganization)
    {
        $this->person_role_organizations->removeElement($personRoleOrganization);
    }

    /**
     * Get personRoleOrganizations
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPersonRoleOrganizations()
    {
        return $this->person_role_organizations;
    }

    /**
     * Get Organizations
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getOrganizations($active = true)
    {
        $orgs = new ArrayCollection();
        foreach ($this->getPersonRoleOrganizations() as $pfo) {
            if ($orgs->contains($pfo->getOrganization()))
                continue;
            $orgs->add($pfo->getOrganization());
        }
        return $orgs;
    }

    /**
     * Add personRoleEvent
     *
     * @param \App\Entity\PersonRoleEvent $personRoleEvent
     *
     * @return Person
     */
    public function addPersonRoleEvent(\App\Entity\PersonRoleEvent $personRoleEvent)
    {
        $this->person_role_events[] = $personRoleEvent;

        return $this;
    }

    /**
     * Remove personRoleEvent
     *
     * @param \App\Entity\PersonRoleEvent $personRoleEvent
     */
    public function removePersonRoleEvent(\App\Entity\PersonRoleEvent $personRoleEvent)
    {
        $this->person_role_events->removeElement($personRoleEvent);
    }

    /**
     * Get personRoleEvents
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPersonRoleEvents()
    {
        return $this->person_role_events;
    }

    /**
     * Get Events
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getEvents($active = true)
    {
        $evts = new ArrayCollection();
        foreach ($this->getPersonRoleEvents() as $pfe) {
            if ($evts->contains($pfe->getEvent()))
                continue;
            $evts->add($pfe->getEvent());
        }
        return $evts;
    }

    /**
     * Add personRoleLocation
     *
     * @param \App\Entity\PersonRoleLocation $personRoleLocation
     *
     * @return Person
     */
    public function addPersonRoleLocation(\App\Entity\PersonRoleLocation $personRoleLocation)
    {
        $this->person_role_locations[] = $personRoleLocation;

        return $this;
    }

    /**
     * Remove personRoleLocation
     *
     * @param \App\Entity\PersonRoleLocation $personRoleLocation
     */
    public function removePersonRoleLocation(\App\Entity\PersonRoleLocation $personRoleLocation)
    {
        $this->person_role_locations->removeElement($personRoleLocation);
    }

    /**
     * Get personRoleLocations
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPersonRoleLocations()
    {
        return $this->person_role_locations;
    }

    /**
     * Get all PersonRoles in one go.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPersonRoles($frog = null)
    {
        // Wanting to use ArrayCollection makes array_merge more annoying.
        $personroles = new ArrayCollection();
        foreach ($this->getPersonRoleOrganizations() as $pro) {
            if ($frog && $frog !== $pro->getOrganization())
                continue;
            $personroles->add($pro);
        }
        foreach ($this->getPersonRoleLocations() as $prl) {
            if ($frog && $frog !== $prl->getLocation())
                continue;
            $personroles->add($prl);
        }
        foreach ($this->getPersonRoleEvents() as $pre) {
            if ($frog && $frog !== $pre->getEvent())
                continue;
            $personroles->add($pre);
        }
        return $personroles;
    }

    /**
     * Get all distinct functions the person has
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getFunctions()
    {
        $functions = new ArrayCollection();
        foreach ($this->getPersonFunctions() as $pf) {
            $f = $pf->getFunction();
            if (!$functions->contains($f))
                $functions->add($f);
        }
        return $functions;
    }

    /**
     * Do the person have it?
     *
     * @return boolean
     */
    public function hasFunction($function)
    {
        if ($function instanceOf FunctionEntity)
            return $this->getFunctions()->contains($function);
        foreach($this->getFunctions() as $func) {
            if (strtolower($func->getName()) == strtolower($function))
                return true;
        }
        return false;
    }

    /**
     * Add job
     *
     * @param \App\Entity\Job $job
     *
     * @return Person
     */
    public function addJob(\App\Entity\Job $job)
    {
        if (!$this->jobs->contains($job)) {
            $this->jobs->add($job);
            $job->setPerson($this);
        }

        return $this;
    }

    /**
     * Remove job
     *
     * @param \App\Entity\Job $job
     */
    public function removeJob(\App\Entity\Job $job)
    {
        if ($this->jobs->contains($job)) {
            $this->jobs->removeElement($job);
        }
    }

    /**
     * Get jobs
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getJobs()
    {
        return $this->jobs;
    }

    /**
     * Get contexts
     *
     * @return objects 
     */
    public function getContexts()
    {
        return $this->contexts;
    }

    /**
     * add context
     *
     * @return mixed 
     */
    public function addContext(PersonContext $context)
    {
        $this->contexts[] = $context;
        $context->setOwner($this) ;
    }

    /**
     * Remove contexts
     *
     * @param PersonContext $contexts
     */
    public function removeContext(PersonContext $contexts)
    {
        $this->contexts->removeElement($contexts);
    }

    /**
     * Is this person a crewe member or not?
     * This is where we decide (for now)
     * Later I have to come up with a filter option for use in the repository
     * calls.
     * Right now it's very simpole and only checks the state of the person.
     * Some day it has to filter on a Role/Funtion in the admins organization.
     * But that is when people can be crew members in more than one
     * organization and we have a multi-org setup. If ever.
     */
    public function isCrew()
    {
        return $this->getState() != "EXTERNAL";
    }

    /*
     * System Roles, more specific name for the UserBundle Roles array.
     * And it makes it easier to separatate from PersonRoles whish is
     * for this application and against Organization, Location and Event.
     *
     * For the form this is called "User Type".
     */

    /**
     * Overrding roles. Need only one role at a time.
     */
    public function setSystemRole($systemRole)
    {
        $this->setRoles([$systemRole]);
        return $this;
    }

    public function getSystemRole()
    {
        return current($this->getRoles());
    }

    public function getSystemRoles()
    {
        return $this->getRoles();
    }

    public function setSystemRoles($systemRoles)
    {
        return $this->getRoles($systemRoles);
    }

    /**
     * Is this deleeteable? If any event connected to it, no.
     *
     * @return boolean
     */
    public function isDeleteable()
    {
        return count($this->getEvents()) == 0 && count($this->getJobs()) == 0;
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function isVerified(): bool
    {
        return $this->is_verified;
    }

    public function setIsVerified(bool $is_verified): self
    {
        $this->is_verified = $is_verified;

        return $this;
    }

    public function getLastLogin(): \Datetime
    {
        return $this->last_login;
    }

    public function setLastLogin(\DateTime $last_login): self
    {
        $this->last_login = $last_login;

        return $this;
    }

    public function __toString()
    {
        return $this->getFullName();
    }
}
