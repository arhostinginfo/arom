# CRM changes request

Text of Sat's Word file "CRM changes.docx" (received 13/10/2026), copied as written so reviewers can read it. Tables and lists are kept; nothing is added or reworded.

## Nursery End-to-End Workflow

New Lead → Site Visit / Requirement Gathering → BOQ + Costing + Quotation  → Approval (amount criteria)→ Quotation Sent → Follow-up → Quotation (version) → PO entry

## New Enquiry – Fields – PAGE SETUP (show/hide, mandatory/optional)

| Field | Configuration | Mandatory |
|---|---|---|
| Enquiry ID | System generated | Mandatory |
| Enquiry Date | Date | Mandatory |
| Source | Dropdown | Mandatory |
| Sub-source / Reference | Text | Optional |
| Competitor details | Text |  |
| Prospect Type | New / Existing Client / Referral | Mandatory |
| Company / Client Name | Text / drop down | Mandatory |
| Industry | Text | Optional |
| Industry Type | Corporate / Mall / Hotel / Hospital / Builder / etc. | Mandatory |
| City | Dropdown | Mandatory |
| Address | Text | Optional |
| Website | URL | Optional |
| Contact NO/NAME | Text | Mandatory |
| Service Type |  Drop down (master) | Mandatory |
| Approx. Requirement | Text/Number | Optional |
| Estimated Value | Currency | Optional |
| Expected Closing Date | Date | Optional |
| Lead Rating | Hot / Warm / Cold | Mandatory |
| Lead assign to | Select from drop down | Mandatory |
| Next Action | Activity manual entry | Mandatory |
| Next Action Date | Date | Mandatory |
| Remarks | Long text | Optional |

## Source Master

Website, Google, Instagram, Facebook, LinkedIn, WhatsApp, Phone Call, Email, Existing Client, Referral, BNI, Event/Exhibition, Architect, Interior Designer, Facility Management Company, Builder, Direct Visit, Other

## Category Master

Plant Rental, Plant AMC, Landscape Design, Landscape Development, Garden Maintenance, Vertical Garden / Green Wall, Terrace Garden, GD DECO, Plant Sale, Planters, Corporate Plantation, Other

## Employee Master: All employee details

## Contact / POC Fields – need to discuss

Contact ID; Name; Designation; Department; Mobile; WhatsApp; Email; Alternate Number; Preferred Communication; Decision Maker?; Influencer?; Purchase Contact?; Facility Contact?; Accounts/Billing Contact?; Notes.

## Site Visit / Requirement Gathering

Required fields: Client; Visit date/time; POC; Technical person; Site address; area/measurements; floors; indoor/outdoor area; existing plants; existing planters; lighting condition; water availability; access restrictions; customer requirement; photos; remarks; next action.

## BOQ Preparation

Upload attachment (optional) – send by mail along with quotation 

## Cost details

Internal fields: Purchase/Material Cost; Plant Production Cost; Planter Cost; Labour Cost; Transportation Cost; Installation Cost; Maintenance Cost; Other Cost; Total Cost; Margin %; Margin Amount; Proposed Selling Price.

## Approval Workflow

Submit costing + BOQ + Quotation (with rev no) for approval (amount criteria)

## Quotation

Header: Quotation No.; Client; Contact; Site; Date; Valid Until; Rev no; Prepared By; Payment Terms; Delivery/Service Terms; GST/Tax; Discount; Notes; Attachments. Line items: Item No; Item Description; Qty; Unit; Rate; Discount; Tax; Total.
Quotation status: Draft → Internal Approval → Approved → Sent → Customer Viewed/Feedback → Negotiation → Accepted / Rejected / Expired/hold with reason

## Follow-up & Activity Management

Activity fields: Follow up details (Call, E-Mail, Whatsapp) with date, Next action and target date with pop up in red color

## PO / Agreement Received

Fields: PO/Agreement No.; PO date; client; site; PO value; PO quantity; PO validity/start/end date; payment terms; GST/tax; PO document; agreement document; customer contact; received by; received date; remarks.

## Documents & Attachments

Enquiry documents; visiting cards; requirement emails; site photos; BOQ; costing approval; quotations and revisions; customer PO; agreement. Every document should store uploader, date/time and linked record.

## Notifications & Alerts

- Follow-up due date – red pop up
- Site visit due
- Quotation pending approval and PO pending

## Dashboards

- Enquiry vs PO received
- Enquiry source performance – pie chart for source wise contribution
- Month wise Enquiry received + Total open leads
- Month wise PO received 
- Pie chart by Category
- Hot leads
- Activities due/overdue
- Verbal wins / PO pending
- Expected closing this month
- Lead ageing report and Lead report (lead to PO all activities status)
