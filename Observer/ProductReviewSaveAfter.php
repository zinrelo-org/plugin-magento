<?php
namespace Zinrelo\LoyaltyRewards\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Zinrelo\LoyaltyRewards\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;

class ProductReviewSaveAfter implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var Http
     */
    private $request;
    /**
     * @var ReviewFactory
     */
    private $reviewFactory;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Product review constructor.
     *
     * @param Data $helper
     * @param ProductRepositoryInterface $productRepository
     * @param ReviewFactory $reviewFactory
     * @param StoreManagerInterface $storeManager
     * @param Http $request
     */
    public function __construct(
        Data $helper,
        ProductRepositoryInterface $productRepository,
        ReviewFactory $reviewFactory,
        StoreManagerInterface $storeManager,
        Http $request
    ) {
        $this->helper = $helper;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->reviewFactory = $reviewFactory;
    }

    /**
     * Product review create/approve event to Zinrelo
     *
     * @param Observer $observer
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        $event = $this->helper->getRewardEvents();
        $reviewObject = $observer->getEvent()->getObject();
        $review = $reviewObject->getData();
        $customerId = $review["customer_id"];
        $productId = $review["entity_pk_value"];
        $reviewId = $review["review_id"];
        $statusId = $review["status_id"];
        $review = $this->reviewFactory->create()->load($reviewId);
        $reviewData = $review->toArray();
        if ($statusId == Review::STATUS_APPROVED && in_array('review_approved', $event, true)) {
            $this->sendRequest($reviewData, $productId, $customerId, "review_approved");
        } elseif ($statusId == Review::STATUS_PENDING &&
            in_array('review_submitted', $event, true) &&
            !$reviewData['submitted_to_zinrelo']
        ) {
            $this->sendRequest($reviewData, $productId, $customerId, "review_submitted");
            try {
                $review->setSubmittedToZinrelo(1);
                $review->save();
            } catch (CouldNotSaveException $e) {
                $this->helper->addErrorLog($e->getMessage());
            }
        }
        return true;
    }

    /**
     * Send review created - approved event to Zinrelo
     *
     * @param array $reviewData
     * @param int $productId
     * @param int $customerId
     * @param string $activityId
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function sendRequest($reviewData, $productId, $customerId, $activityId): bool
    {
        $reviewData['product_url'] = $this->helper->getProductUrl($productId);
        $reviewData['product_image_url'] = $this->helper->getProductImageUrl($productId);
        $categoryData = $this->helper->getCategoryData($productId);
        $reviewData['category_name'] = $categoryData['name'];
        $reviewData['category_ids'] = $categoryData['ids'];
        $params = [
            "member_id" => $this->helper->getCustomerEmailById($customerId),
            "activity_id" => $activityId,
            "data" => $reviewData
        ];
        $url = $this->helper->getWebHookUrl();
        $params = $this->helper->json->serialize($params);
        $this->helper->request($url, $params, "post");
        return true;
    }
}
