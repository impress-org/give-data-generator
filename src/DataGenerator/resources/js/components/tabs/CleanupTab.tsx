import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
    Button,
    Notice,
    Card,
    CardBody,
    CardHeader
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { ApiResponse, ResultState } from '../../types';
import dataGenerator from '../../common/getWindowData';

interface CleanupAction {
    id: string;
    title: string;
    description: string;
    buttonText: string;
}

const CleanupTab: React.FC = () => {
    const { invalidateResolution } = useDispatch('core');
    const [isSubmitting, setIsSubmitting] = useState<Record<string, boolean>>({});
    const [result, setResult] = useState<ResultState | null>(null);

    const handleCleanupAction = async (actionType: string): Promise<void> => {
        // Ask for confirmation
        const confirmMessages: Record<string, string> = {
            'delete_test_donations': __('Are you sure you want to delete all test mode donations? This action cannot be undone.', 'give-data-generator'),
            'delete_test_subscriptions': __('Are you sure you want to delete all test mode subscriptions? This action cannot be undone.', 'give-data-generator'),
            'archive_campaigns': __('Are you sure you want to archive all active campaigns? This action cannot be undone.', 'give-data-generator')
        };

        if (!confirm(confirmMessages[actionType])) {
            return;
        }

        setIsSubmitting(prev => ({ ...prev, [actionType]: true }));
        setResult(null);

        try {
            const params = new URLSearchParams({
                action: 'cleanup_test_data',
                nonce: dataGenerator.cleanupNonce,
                action_type: actionType
            });

            const response = await fetch(dataGenerator.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: params
            });

            const data: ApiResponse = await response.json();

            setResult({
                success: data.success,
                message: data.data?.message || 'Operation completed'
            });

            if (data.success && actionType === 'archive_campaigns') {
                // Invalidate campaign cache since archiving campaigns affects the active campaigns list
                invalidateResolution('getEntityRecords', [
                    'givewp',
                    'campaign',
                    {
                        status: ['active'],
                        per_page: 100,
                        orderby: 'date',
                        order: 'desc',
                    }
                ]);
            }
        } catch (error) {
            console.error('Cleanup action error:', error);
            setResult({
                success: false,
                message: error instanceof Error ? error.message : 'An error occurred'
            });
        } finally {
            setIsSubmitting(prev => ({ ...prev, [actionType]: false }));
        }
    };

    const cleanupActions: CleanupAction[] = [
        {
            id: 'delete_test_donations',
            title: __('Delete Test Mode Donations', 'give-data-generator'),
            description: __('This will permanently delete all donations that were made in test mode, including their related donors.', 'give-data-generator'),
            buttonText: __('Delete Test Donations', 'give-data-generator')
        },
        {
            id: 'delete_test_subscriptions',
            title: __('Delete Test Mode Subscriptions', 'give-data-generator'),
            description: __('This will permanently delete all subscriptions that were created in test mode, along with their related donations.', 'give-data-generator'),
            buttonText: __('Delete Test Subscriptions', 'give-data-generator')
        },
        {
            id: 'archive_campaigns',
            title: __('Archive All Campaigns', 'give-data-generator'),
            description: __('This will archive all active campaigns. Archived campaigns will no longer be accessible for donations.', 'give-data-generator'),
            buttonText: __('Archive Campaigns', 'give-data-generator')
        }
    ];

    return (
        <Card>
            <CardHeader>
                <h2>{__('Clean Up Test Data', 'give-data-generator')}</h2>
            </CardHeader>
            <CardBody>
                <div style={{ marginBottom: '20px', padding: '15px', backgroundColor: '#fff3cd', border: '1px solid #ffeaa7', borderRadius: '4px' }}>
                    <strong style={{ color: '#856404' }}>⚠️ {__('Warning:', 'give-data-generator')}</strong>
                    <p style={{ color: '#856404', margin: '5px 0 0 0' }}>
                        {__('These actions are permanent and cannot be undone. Please ensure you have a backup before proceeding.', 'give-data-generator')}
                    </p>
                </div>

                <div style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
                    gap: '20px',
                    marginTop: '20px'
                }}>
                    {cleanupActions.map((action: CleanupAction) => (
                        <div key={action.id} style={{
                            background: '#fff',
                            border: '1px solid #c3c4c7',
                            borderRadius: '5px',
                            padding: '20px',
                            boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)'
                        }}>
                            <h3 style={{ marginTop: 0, color: '#d63638' }}>
                                {action.title}
                            </h3>
                            <p style={{ color: '#646970', marginBottom: '15px' }}>
                                {action.description}
                            </p>
                            <Button
                                variant="secondary"
                                isBusy={isSubmitting[action.id] || false}
                                disabled={isSubmitting[action.id] || false}
                                onClick={() => handleCleanupAction(action.id)}
                                style={{
                                    backgroundColor: '#d63638',
                                    borderColor: '#d63638',
                                    color: '#fff'
                                }}
                            >
                                {action.buttonText}
                            </Button>
                        </div>
                    ))}
                </div>

                {result && (
                    <div style={{ marginTop: '20px' }}>
                        <Notice
                            status={result.success ? 'success' : 'error'}
                            isDismissible={true}
                            onRemove={() => setResult(null)}
                        >
                            {result.message}
                        </Notice>
                    </div>
                )}
            </CardBody>
        </Card>
    );
};

export default CleanupTab;
