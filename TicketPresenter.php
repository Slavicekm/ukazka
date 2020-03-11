<?php
declare(strict_types=1);

namespace App\Presenters;

use App\Form\Ticket\FilterTicketForm;
use App\Components\Paginator;
use App\Model\Ticket;
use App\Repository\PackageRepository;
use App\Repository\TicketRepository;
use App\Services\Limiter;
use Doctrine\ORM\EntityManager;
use Nette\Forms\Form;
use Nette\Http\Url;
use WebLoader\Engine;

/**
 * Class TicketPresenter
 * @package App\AdminModule\Presenters
 */
class TicketPresenter extends IdentityCheckPresenter
{

    /** @var TicketRepository @inject */
    public $ticketRepository;

    /** @var PackageRepository @inject */
    public $packageRepository;

    /**
     * @var Limiter
     */
    private $limiter;

    /**
     * TicketPresenter constructor.
     * @param EntityManager $em
     * @param Limiter $limiter
     * @param Engine $engine
     */
    public function __construct(EntityManager $em, Limiter $limiter, Engine $engine)
    {
        parent::__construct($em, $engine);
        $this->limiter = $limiter;
    }

    /**
     * @var Ticket
     */
    private $ticket;

    public function renderDefault()
    {
        $request = $this->getHttpRequest();
        $this->limiter->initByRequest($request);
        $tickets = $this->ticketRepository->findLimitedHistoryTickets($this->limiter);

        $uri = $request->getUrl();
        $url = new Url($uri);
        $link = $url->path;

        $paginator = new Paginator();
        $paginator->setItemCount($this->limiter->getTotal());
        $paginator->setPage($this->limiter->getPage());
        $paginator->setItemsPerPage($this->limiter->getLimit());

        $this['filterTicketForm']->setDefaults($this->limiter->getCriteria());

        $this->template->paginator = $paginator;
        $this->template->link = $link;
        $this->template->tickets = $tickets;
        $this->template->title = 'Historie tiketů';
        $this->template->limiter = $this->limiter;
        $this->template->bodyClass = 'p-historie t-system';
        $this->template->packages = $this->packageRepository->findActivePackages();
        $this->template->currPackage = $this->limiter->getCriteriaByIndex('packageId');

        if ($this->isAjax()) {
            $this->payload->history = $link . $this->limiter->buildUrlQuery();
            $this->payload->scrollTo = '#snippet--historyTickets';
            $this->redrawControl('historyTickets');
            $this->redrawControl('filter');
        }
    }

    /**
     * @return Form
     */
    protected function createComponentFilterTicketForm(): Form
    {
        return (new FilterTicketForm())->create();
    }

    /**
     * @param int $id
     */
    public function actionDetail(int $id)
    {
        $ticket = $this->ticketRepository->findTicketById($id);

        if (is_null($ticket)) {
            $this->redirect('Dashboard:default');
        }

        $allowedPackages = $this->clientUser->getActivePackagesId();

        if (!in_array($ticket->package->id, $allowedPackages)) {
            $this->redirect('Dashboard:default');
        }

        $this->ticket = $ticket;
    }

    /**
     * @param int $id
     */
    public function renderDetail(int $id)
    {
        $this->template->ticket = $this->ticket;
    }

}
