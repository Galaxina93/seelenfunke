import os
import re

replacements = {
    r'App\\Models\\Accounting\\BankAccount': r'App\\Models\\Accounting\\AccountingBankAccount',
    r'App\\Models\\Accounting\\BankTransaction': r'App\\Models\\Accounting\\AccountingBankTransaction',
    r'App\\Models\\Accounting\\FinanceCategorizationRule': r'App\\Models\\Accounting\\AccountingCategorizationRule',
    r'App\\Models\\Accounting\\FinanceCategory': r'App\\Models\\Accounting\\AccountingCategory',
    r'App\\Models\\Accounting\\FinanceCostItem': r'App\\Models\\Accounting\\AccountingCostItem',
    r'App\\Models\\Accounting\\FinanceCostItemHistory': r'App\\Models\\Accounting\\AccountingCostItemHistory',
    r'App\\Models\\Accounting\\FinanceGroup': r'App\\Models\\Accounting\\AccountingGroup',
    r'App\\Models\\Accounting\\FinanceSpecialIssue': r'App\\Models\\Accounting\\AccountingSpecialIssue',
    r'App\\Models\\Accounting\\Invoice': r'App\\Models\\Accounting\\AccountingInvoice',
    
    r'(?<!Accounting)BankAccount::': r'AccountingBankAccount::',
    r'(?<!Accounting)BankTransaction::': r'AccountingBankTransaction::',
    r'FinanceCategorizationRule::': r'AccountingCategorizationRule::',
    r'FinanceCategory::': r'AccountingCategory::',
    r'FinanceCostItem::': r'AccountingCostItem::',
    r'FinanceCostItemHistory::': r'AccountingCostItemHistory::',
    r'FinanceGroup::': r'AccountingGroup::',
    r'FinanceSpecialIssue::': r'AccountingSpecialIssue::',
    r'(?<!Accounting)Invoice::': r'AccountingInvoice::',
    
    r'\$invoice->': r'$invoice->', # Just keeping variables lowercase
    r'finance_category_id': r'accounting_category_id',
    r'finance_cost_item_id': r'accounting_cost_item_id',
    r'finance_group_id': r'accounting_group_id'
}

for root, _, files in os.walk('app'):
    for file in files:
        if file.endswith('.php'):
            path = os.path.join(root, file)
            with open(path, 'r', encoding='utf-8') as f:
                content = f.read()
            
            new_content = content
            for old, new in replacements.items():
                new_content = re.sub(old, new, new_content)
                
            if new_content != content:
                with open(path, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                print(f"Updated: {path}")

for root, _, files in os.walk('resources/views'):
    for file in files:
        if file.endswith('.php'):
            path = os.path.join(root, file)
            with open(path, 'r', encoding='utf-8') as f:
                content = f.read()
            
            new_content = content
            for old, new in replacements.items():
                new_content = re.sub(old, new, new_content)
                
            if new_content != content:
                with open(path, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                print(f"Updated: {path}")
